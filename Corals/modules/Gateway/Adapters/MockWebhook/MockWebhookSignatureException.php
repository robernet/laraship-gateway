<?php

namespace Corals\Modules\Gateway\Adapters\MockWebhook;

use RuntimeException;

/**
 * Thrown by MockWebhookAdapter when a push is unsigned or its signature does
 * not match the shared secret. Thrown BEFORE the AdapterGateway is called,
 * so no Core state is ever touched.
 */
final class MockWebhookSignatureException extends RuntimeException
{
}
