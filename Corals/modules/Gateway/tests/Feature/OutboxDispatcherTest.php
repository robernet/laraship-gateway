<?php

namespace Tests\Feature;

use Corals\Foundation\Facades\Actions;
use Corals\Modules\Gateway\Core\Outbox\OutboxDispatcher;
use Corals\Modules\Gateway\Models\OutboxEvent;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use RuntimeException;

class OutboxDispatcherTest extends GatewayTestCase
{
    public function test_dispatches_all_pending_events_in_order(): void
    {
        $seen = [];
        Actions::add_action('test.outbox.happy', function ($payload) use (&$seen) {
            $seen[] = $payload;
        });

        $first = OutboxEvent::create(['event' => 'test.outbox.happy', 'payload' => ['n' => 1], 'status' => 'pending']);
        $second = OutboxEvent::create(['event' => 'test.outbox.happy', 'payload' => ['n' => 2], 'status' => 'pending']);

        $dispatched = (new OutboxDispatcher())->dispatchPending();

        $this->assertSame(2, $dispatched);
        $this->assertSame([['n' => 1], ['n' => 2]], $seen);
        $this->assertSame('dispatched', $first->fresh()->status);
        $this->assertSame('dispatched', $second->fresh()->status);
        $this->assertNotNull($first->fresh()->dispatched_at);
    }

    /**
     * The literal GW-303 done-when: simulate a crash mid-delivery (the
     * second event's listener throws) and prove the already-delivered event
     * stays dispatched, the crashed one and everything after it stay
     * pending untouched, and — critically — nothing is lost: a later retry
     * picks the pending event back up and delivers it.
     */
    public function test_a_crash_between_commit_and_delivery_loses_no_event(): void
    {
        $shouldFail = true;
        Actions::add_action('test.outbox.crash', function () use (&$shouldFail) {
            if ($shouldFail) {
                throw new RuntimeException('simulated crash during delivery');
            }
        });

        $delivered = OutboxEvent::create(['event' => 'test.outbox.ok', 'payload' => [], 'status' => 'pending']);
        $crashesHere = OutboxEvent::create(['event' => 'test.outbox.crash', 'payload' => [], 'status' => 'pending']);
        $neverReached = OutboxEvent::create(['event' => 'test.outbox.ok', 'payload' => [], 'status' => 'pending']);

        try {
            (new OutboxDispatcher())->dispatchPending();
            $this->fail('Expected the simulated crash to propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated crash during delivery', $e->getMessage());
        }

        $this->assertSame('dispatched', $delivered->fresh()->status);
        $this->assertSame('pending', $crashesHere->fresh()->status);
        $this->assertSame('pending', $neverReached->fresh()->status);

        // The "crash" is over; delivery succeeds on retry — no event was lost.
        $shouldFail = false;
        $dispatchedOnRetry = (new OutboxDispatcher())->dispatchPending();

        $this->assertSame(2, $dispatchedOnRetry);
        $this->assertSame('dispatched', $crashesHere->fresh()->status);
        $this->assertSame('dispatched', $neverReached->fresh()->status);
    }

    public function test_dispatch_pending_ignores_already_dispatched_events(): void
    {
        Actions::add_action('test.outbox.idempotent', function () {
            //
        });

        $already = OutboxEvent::create([
            'event' => 'test.outbox.idempotent',
            'payload' => [],
            'status' => 'dispatched',
            'dispatched_at' => now()->subHour(),
        ]);

        $dispatched = (new OutboxDispatcher())->dispatchPending();

        $this->assertSame(0, $dispatched);
        $this->assertSame('dispatched', $already->fresh()->status);
    }
}
