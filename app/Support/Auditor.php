<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes append-only entries to {@see AuditLog}. Actor + tenant + request
 * metadata are captured server-side; callers pass only the event and subject.
 */
class Auditor
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $event, ?Model $subject = null, array $properties = [], ?string $description = null): AuditLog
    {
        $actor = Auth::user();
        $tenantId = app(TenantContext::class)->id()
            ?? ($actor instanceof User ? $actor->tenant_id : null);

        return AuditLog::query()->create([
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
    }

    private function actorLabel(?object $actor): string
    {
        if ($actor instanceof User) {
            return $actor->name.' <'.$actor->email.'>';
        }

        return $actor !== null ? class_basename($actor) : 'system';
    }
}
