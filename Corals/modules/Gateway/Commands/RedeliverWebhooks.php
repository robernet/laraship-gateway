<?php

namespace Corals\Modules\Gateway\Commands;

use Corals\Modules\Gateway\Core\Webhooks\WebhookDispatcher;
use Illuminate\Console\Command;

/**
 * Scheduled retry sweep for GW-304: picks up webhook_deliveries left
 * `pending` past their next_retry_at (failed attempt, exponential backoff)
 * and redelivers them, resending the same signed envelope byte-for-byte.
 */
class RedeliverWebhooks extends Command
{
    protected $signature = 'gateway:redeliver-webhooks';

    protected $description = 'Retries pending webhook deliveries whose backoff window has elapsed.';

    public function handle(WebhookDispatcher $dispatcher): int
    {
        $count = $dispatcher->retryPending();

        $this->info("Retried {$count} webhook deliveries.");

        return self::SUCCESS;
    }
}
