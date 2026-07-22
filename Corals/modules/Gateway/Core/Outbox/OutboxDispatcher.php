<?php

namespace Corals\Modules\Gateway\Core\Outbox;

use Corals\Foundation\Facades\Actions;
use Corals\Modules\Gateway\Models\OutboxEvent;

/**
 * Delivers pending outbox_events (module CLAUDE.md: state-changing writes go
 * through Core in a DB transaction; fanout to webhooks/notifications/audit
 * happens after, via the Actions hook bus — never inline in the posting
 * itself). A row is only marked dispatched AFTER Actions::dispatch()
 * returns successfully; durability comes from that ordering, not from a
 * try/catch — if delivery throws (a "crash"), the row is simply left
 * pending for the next run. No event is ever marked dispatched without
 * having actually been dispatched.
 */
class OutboxDispatcher
{
    public function dispatchPending(int $limit = 50): int
    {
        $dispatched = 0;

        $events = OutboxEvent::where('status', 'pending')->orderBy('id')->limit($limit)->get();

        foreach ($events as $event) {
            Actions::dispatch($event->event, [$event->payload]);

            $event->update(['status' => 'dispatched', 'dispatched_at' => now()]);
            $dispatched++;
        }

        return $dispatched;
    }
}
