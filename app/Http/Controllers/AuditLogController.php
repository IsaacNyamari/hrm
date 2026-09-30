<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\AuditLog;
use App\Support\Auditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /**
     * @return Builder<AuditLog>
     */
    private function filtered(Request $request): Builder
    {
        return AuditLog::query()
            ->where('tenant_id', Tenancy::id())
            ->when($request->filled('event'), fn ($q) => $q->where('event', 'like', $request->string('event')->toString().'%'))
            ->when($request->filled('actor_id'), fn ($q) => $q->where('actor_id', $request->integer('actor_id')))
            ->when($request->filled('actor'), fn ($q) => $q->where('actor_label', 'like', '%'.$request->string('actor')->toString().'%'))
            ->when($request->filled('auditable_type'), fn ($q) => $q->where('auditable_type', $request->string('auditable_type')->toString()))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('from')->toString()))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('to')->toString()));
    }

    /**
     * The whole filtered trail as CSV, for auditors. Exporting is itself recorded.
     */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('audit.view') && $request->user()->can('reports.export'), 403);

        $query = $this->filtered($request)->latest('id');

        app(Auditor::class)->log('audit.exported', null, ['filters' => $request->only(['event', 'actor', 'from', 'to'])]);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'Event', 'Actor', 'Subject type', 'Subject id', 'Description', 'IP address', 'Impersonated', 'Details']);

            $query->chunkById(500, function ($logs) use ($out): void {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $log->created_at?->toIso8601String(), $log->event, $log->actor_label,
                        $log->auditable_type !== null ? class_basename($log->auditable_type) : '', $log->auditable_id,
                        $log->description, $log->ip_address, $log->via_impersonation ? 'yes' : 'no',
                        $log->properties === null ? '' : json_encode($log->properties),
                    ]);
                }
            });

            fclose($out);
        }, 'audit-trail-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        $logs = $this->filtered($request)
            ->latest('id')
            ->paginate(min($request->integer('per_page', 50), 200));

        return response()->json($logs->through(fn (AuditLog $log): array => [
            'uuid' => $log->uuid,
            'event' => $log->event,
            'actor' => $log->actor_label,
            'auditable_type' => $log->auditable_type !== null ? class_basename($log->auditable_type) : null,
            'auditable_id' => $log->auditable_id,
            'description' => $log->description,
            'properties' => $log->properties,
            'ip_address' => $log->ip_address,
            'via_impersonation' => $log->via_impersonation,
            'created_at' => $log->created_at?->toIso8601String(),
        ])->toArray());
    }

    public function show(Request $request, AuditLog $auditLog): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);
        abort_unless($auditLog->tenant_id === Tenancy::id(), 404);

        return response()->json(['data' => [
            'uuid' => $auditLog->uuid,
            'event' => $auditLog->event,
            'actor' => $auditLog->actor_label,
            'auditable_type' => $auditLog->auditable_type,
            'auditable_id' => $auditLog->auditable_id,
            'description' => $auditLog->description,
            'properties' => $auditLog->properties,
            'ip_address' => $auditLog->ip_address,
            'user_agent' => $auditLog->user_agent,
            'via_impersonation' => $auditLog->via_impersonation,
            'created_at' => $auditLog->created_at?->toIso8601String(),
        ]]);
    }
}
