<?php

namespace Corals\Modules\Gateway\Core\Webhooks;

use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Signs and delivers issuer webhooks per contracts/openapi.yaml's
 * PaymentWebhookEnvelope / SettlementWebhookEnvelope: contract_v + event +
 * the caller's business fields, plus timestamp/nonce/signature added here.
 * `signature` is an HMAC-SHA256 over the canonical (insertion-order) JSON of
 * every other envelope field — same scheme as QrPayload/QrVerifier (GW-204),
 * so a receiver verifies by recomputing the HMAC over all fields except
 * `signature` with the shared webhook_secret.
 *
 * The nonce is generated once per delivery and reused verbatim on every
 * retry (never regenerated), so a receiver that dedupes on nonce treats a
 * legitimate retry as an idempotent no-op instead of a second event — the
 * same nonce store doubles as replay detection for a truly duplicated
 * payload.
 */
class WebhookDispatcher
{
    private const MAX_ATTEMPTS = 8; // ponytail: fixed cap; make configurable if issuers need per-tenant tuning

    public function notify(string $event, array $payload): ?WebhookDelivery
    {
        $issuer = Issuer::find($payload['issuer_id'] ?? null);

        if (! $issuer || ! $issuer->webhook_url || ! $issuer->webhook_secret) {
            return null;
        }

        $envelope = array_merge(
            ['contract_v' => 1, 'event' => $event],
            $payload,
            ['timestamp' => now()->toIso8601String(), 'nonce' => bin2hex(random_bytes(16))]
        );

        $envelope['signature'] = $this->sign($envelope, $issuer->webhook_secret);

        $delivery = WebhookDelivery::create([
            'issuer_id' => $issuer->id,
            'event' => $event,
            'payload' => $envelope,
            'signature' => $envelope['signature'],
            'status' => 'pending',
        ]);

        $this->attempt($delivery, $issuer);

        return $delivery->fresh();
    }

    public function attempt(WebhookDelivery $delivery, ?Issuer $issuer = null): void
    {
        $issuer ??= $delivery->issuer;

        try {
            $response = Http::post($issuer->webhook_url, $delivery->payload);
            $ok = $response->successful();
            $error = $ok ? null : "HTTP {$response->status()}";
        } catch (Throwable $e) {
            $ok = false;
            $error = $e->getMessage();
        }

        if ($ok) {
            $delivery->update(['status' => 'delivered', 'delivered_at' => now(), 'last_error' => null]);

            return;
        }

        $attempts = $delivery->attempts + 1;

        $delivery->update([
            'attempts' => $attempts,
            'last_error' => $error,
            'status' => $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending',
            'next_retry_at' => $attempts >= self::MAX_ATTEMPTS ? null : now()->addSeconds($this->backoffSeconds($attempts)),
        ]);
    }

    /**
     * Redelivers everything due for retry. Called from the
     * gateway:redeliver-webhooks scheduled command.
     */
    public function retryPending(int $limit = 50): int
    {
        $deliveries = WebhookDelivery::where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($deliveries as $delivery) {
            $this->attempt($delivery);
        }

        return $deliveries->count();
    }

    private function sign(array $envelope, string $secret): string
    {
        return hash_hmac('sha256', json_encode($envelope, JSON_UNESCAPED_SLASHES), $secret);
    }

    private function backoffSeconds(int $attempts): int
    {
        return min(30 * (2 ** $attempts), 3600);
    }
}
