<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\HrRequestStatus;
use App\Enums\HrRequestType;
use App\Events\HrRequestSubmitted;
use App\Facades\Tenancy;
use App\Models\HrRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class HrRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrRequest::class);

        $user = $request->user();
        $isHr = $user?->can('employees.view') || $user?->can('users.manage');
        $ownEmployeeId = $user?->employee?->getKey();

        $requests = HrRequest::query()
            ->with('employee:id,first_name,last_name,employee_number')
            ->when(! $isHr, fn ($q) => $q->where(fn ($inner) => $inner->where('employee_id', $ownEmployeeId)->orWhere('assigned_to', $user?->getKey())))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->get();

        return response()->json(['data' => $requests->map(fn (HrRequest $r): array => $this->transform($r))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', HrRequest::class);

        $data = $request->validate([
            'type' => ['required', new Enum(HrRequestType::class)],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:low,normal,high'],
        ]);

        $employee = $request->user()?->employee;
        abort_if($employee === null, 422, 'Your account is not linked to an employee record.');

        $tenantId = Tenancy::id();
        $sequence = HrRequest::query()->count() + 1;

        $hrRequest = HrRequest::query()->create([
            'employee_id' => $employee->getKey(),
            'reference' => sprintf('HR-%06d', $sequence),
            'type' => $data['type'],
            'subject' => $data['subject'],
            'body' => $data['body'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'status' => HrRequestStatus::Open,
        ]);

        HrRequestSubmitted::dispatch($hrRequest);

        return response()->json(['data' => $this->transform($hrRequest)], 201);
    }

    public function show(HrRequest $hrRequest): JsonResponse
    {
        $this->authorize('view', $hrRequest);

        $hrRequest->load(['comments.user:id,name', 'employee', 'assignee:id,name']);

        $showInternal = request()->user()?->can('employees.view') || $hrRequest->assigned_to === request()->user()?->getKey();

        return response()->json(['data' => $this->transform($hrRequest, detailed: true) + [
            'comments' => $hrRequest->comments
                ->filter(fn ($c) => ! $c->is_internal || $showInternal)
                ->map(fn ($c): array => [
                    'author' => $c->user?->name,
                    'body' => $c->body,
                    'internal' => $c->is_internal,
                    'at' => $c->created_at?->toIso8601String(),
                ])->values()->all(),
        ]]);
    }

    public function update(Request $request, HrRequest $hrRequest): JsonResponse
    {
        $this->authorize('manage', $hrRequest);

        $data = $request->validate([
            'status' => ['sometimes', new Enum(HrRequestStatus::class)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', Tenancy::id())],
            'resolution_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (array_key_exists('assigned_to', $data)) {
            $hrRequest->assigned_to = $data['assigned_to'];
            if ($data['assigned_to'] !== null && $hrRequest->status === HrRequestStatus::Open) {
                $hrRequest->status = HrRequestStatus::Assigned;
            }
        }

        if (isset($data['status'])) {
            $status = HrRequestStatus::from($data['status']);
            $hrRequest->status = $status;
            $hrRequest->resolved_at = $status->isTerminal() ? CarbonImmutable::now() : null;
        }

        $hrRequest->resolution_note = $data['resolution_note'] ?? $hrRequest->resolution_note;
        $hrRequest->save();

        return response()->json(['data' => $this->transform($hrRequest)]);
    }

    public function comment(Request $request, HrRequest $hrRequest): JsonResponse
    {
        $this->authorize('comment', $hrRequest);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'internal' => ['boolean'],
        ]);

        $internal = (bool) ($data['internal'] ?? false)
            && ($request->user()?->can('employees.view') || $hrRequest->assigned_to === $request->user()?->getKey());

        $hrRequest->comments()->create([
            'tenant_id' => $hrRequest->tenant_id,
            'user_id' => $request->user()?->getKey(),
            'body' => $data['body'],
            'is_internal' => $internal,
        ]);

        if ($hrRequest->status === HrRequestStatus::Assigned) {
            $hrRequest->forceFill(['status' => HrRequestStatus::InProgress])->save();
        }

        return response()->json(['data' => $this->transform($hrRequest->fresh())], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(HrRequest $r, bool $detailed = false): array
    {
        $base = [
            'uuid' => $r->uuid,
            'reference' => $r->reference,
            'type' => $r->type->value,
            'subject' => $r->subject,
            'priority' => $r->priority,
            'status' => $r->status->value,
            'employee' => $r->relationLoaded('employee') ? $r->employee->fullName() : null,
            'created_at' => $r->created_at?->toIso8601String(),
        ];

        return $detailed
            ? $base + ['body' => $r->body, 'resolution_note' => $r->resolution_note, 'resolved_at' => $r->resolved_at?->toIso8601String()]
            : $base;
    }
}
