<?php

namespace Corals\Modules\Gateway\Adapters\MockRealtime;

use RuntimeException;

/**
 * Thrown by MockRealtimeAdapter when constructed with simulateTimeout: true
 * — simulates the network never responding. Thrown BEFORE the AdapterGateway
 * is called, so no Core state is ever touched; a caller sees this exactly
 * like a real network timeout and must decide whether to retry (with the
 * same idempotency_key/network_txn_id for confirm) or surface a failure.
 */
final class MockRealtimeTimeoutException extends RuntimeException
{
}
