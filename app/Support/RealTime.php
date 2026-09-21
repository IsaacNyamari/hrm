<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pushes an event to connected browsers once the surrounding transaction has
 * committed (so listeners re-reading the database see the change). Real-time
 * updates are best effort: if the WebSocket server is down the user action
 * still succeeds and the failure is only logged.
 */
final class RealTime
{
    public static function send(ShouldBroadcastNow $event): void
    {
        DB::afterCommit(function () use ($event): void {
            try {
                event($event);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
