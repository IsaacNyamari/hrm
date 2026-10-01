<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use WeakMap;

/**
 * Writes append-only entries to {@see AuditLog}. Actor + tenant + request
 * metadata are captured server-side; callers pass only the event and subject.
 */
class Auditor
{
    private static int $mutedDepth = 0;

    /** @var WeakMap<object, array<string, AuditLog>>|null */
    private static ?WeakMap $logged = null;

    /**
     * Run a bulk system operation (tenant provisioning, seeding) without
     * filling the trail with rows nobody acted on. Model-change auditing is
     * paused for the duration; explicit log() calls are unaffected.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function muted(callable $callback): mixed
    {
        self::$mutedDepth++;

        try {
            return $callback();
        } finally {
            self::$mutedDepth--;
        }
    }

    public static function isMuted(): bool
    {
        return self::$mutedDepth > 0;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $event, ?Model $subject = null, array $properties = [], ?string $description = null): AuditLog
    {
        $actor = Auth::user();
        $tenantId = app(TenantContext::class)->id()
            ?? ($actor instanceof User ? $actor->tenant_id : null);

        $fingerprint = $this->fingerprint($event, $subject, $properties, $actor, $tenantId);
        $request = request();
        self::$logged ??= new WeakMap;

        if (isset(self::$logged[$request][$fingerprint])) {
            return self::$logged[$request][$fingerprint];
        }

        $entry = AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'event' => $event,
            'actor_type' => $actor !== null ? $actor->getMorphClass() : null,
            'actor_id' => $actor?->getKey(),
            'actor_label' => $this->actorLabel($actor),
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 512),
            'via_impersonation' => session()->has('impersonator_id'),
            'created_at' => CarbonImmutable::now(),
        ]);

        $logged = self::$logged[$request] ?? [];
        $logged[$fingerprint] = $entry;
        self::$logged[$request] = $logged;

        return $entry;
    }

    /**
     * One request must never record the same event for the same item twice
     * (a model saved repeatedly, or an event raised by both a model hook and
     * an explicit call).
     *
     * @param  array<string, mixed>  $properties
     */
    private function fingerprint(string $event, ?Model $subject, array $properties, ?object $actor, int|string|null $tenantId): string
    {
        return md5(serialize([
            $event, $subject?->getMorphClass(), $subject?->getKey(), $properties,
            $actor instanceof Model ? $actor->getKey() : null, $tenantId,
        ]));
    }

    private function actorLabel(?object $actor): string
    {
        if ($actor instanceof User) {
            return $actor->name.' <'.$actor->email.'>';
        }

        return $actor !== null ? class_basename($actor) : 'system';
    }
}
