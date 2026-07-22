<?php

namespace Corals\Modules\Gateway\Core\Intents;

use Corals\Modules\Gateway\Core\References\QrPayload;
use Corals\Modules\Gateway\Core\References\ReferenceGenerator;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\MerchantKey;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a payment intent + its first reference for the authenticated
 * issuer. `mid` routes to a Merchant; ownership (merchant.issuer_id ===
 * issuer.id) is enforced here rather than via a BasePolicy — BasePolicy's
 * before()/admin() hooks assume a permission-bearing web User
 * (hasPermissionTo/isSuperUser), which doesn't fit an Issuer API credential.
 */
class CreatePaymentIntent
{
    public function handle(Issuer $issuer, array $data): PaymentIntent
    {
        $merchant = Merchant::where('mid', $data['mid'])->first();

        if (! $merchant || $merchant->issuer_id !== $issuer->id) {
            throw new AuthorizationException('mid does not belong to the authenticated issuer.');
        }

        return DB::transaction(function () use ($issuer, $merchant, $data) {
            $intent = PaymentIntent::create([
                'issuer_id' => $issuer->id,
                'merchant_id' => $merchant->id,
                'invoice_id' => $data['invoice_id'],
                'mode' => $data['mode'],
                'amount_policy' => $data['amount_policy'],
                'mapping_strategy' => $data['mapping_strategy'] ?? 'deterministic',
                'state' => 'ACTIVE',
                'expires_at' => $data['expires_at'] ?? null,
                'max_payments' => $data['max_payments'] ?? null,
                'overpay_policy' => $data['overpay_policy'] ?? 'reject',
                'underpay_policy' => $data['underpay_policy'] ?? 'reject',
            ]);

            $reference = (new ReferenceGenerator())->generate($intent);

            // QR signing needs a provisioned merchant key; not every merchant
            // has one yet, so this is best-effort (human_reference + barcode
            // always exist regardless).
            $signingKey = MerchantKey::where('merchant_id', $merchant->id)->where('state', 'active')->first();

            if ($signingKey) {
                QrPayload::issue($reference, $signingKey);
            }

            return $intent->load('paymentReferences');
        });
    }
}
