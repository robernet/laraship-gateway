<?php

namespace Corals\Modules\Gateway\Adapters\MockWebhook;

use RuntimeException;

/**
 * Thrown by MockWebhookAdapter when a push repeats a `txn_id` already
 * accepted within the replay window. Thrown BEFORE the AdapterGateway is
 * called — defense-in-depth ahead of Core's own network_txn_id idempotency
 * check (Core\Collections\ConfirmCollection), not a replacement for it.
 */
final class MockWebhookReplayException extends RuntimeException
{
}
