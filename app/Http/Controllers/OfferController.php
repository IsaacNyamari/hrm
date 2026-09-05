<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Recruitment\ConvertOfferToEmployee;
use App\Facades\Tenancy;
use App\Models\Application;
use App\Models\Offer;
use App\Services\Recruitment\RecruitmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfferController extends Controller
{
    public function __construct(private readonly RecruitmentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Offer::class);

        $offers = Offer::query()
            ->with(['application.applicant:id,first_name,last_name', 'application.vacancy:id,title'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $offers->map(fn (Offer $o): array => $this->transform($o))]);
    }

    public function show(Offer $offer): JsonResponse
    {
        $this->authorize('view', $offer);

        return response()->json(['data' => $this->transform($offer->load(['application.applicant', 'application.vacancy']), detailed: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'application_uuid' => ['required', Rule::exists('applications', 'uuid')->where('tenant_id', Tenancy::id())],
            'job_title' => ['nullable', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', Tenancy::id())],
            'designation_id' => ['nullable', 'integer', Rule::exists('designations', 'id')->where('tenant_id', Tenancy::id())],
            'employment_type_id' => ['nullable', 'integer', 'exists:employment_types,id'],
            'salary_cents' => ['required', 'integer', 'min:1'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'start_date' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $application = Application::query()->where('uuid', $data['application_uuid'])->firstOrFail();
        $this->authorize('manage', $application);

        $offer = $this->service->makeOffer($application, [
            'job_title' => $data['job_title'] ?? null,
            'department_id' => isset($data['department_id']) ? (int) $data['department_id'] : null,
            'designation_id' => isset($data['designation_id']) ? (int) $data['designation_id'] : null,
            'employment_type_id' => isset($data['employment_type_id']) ? (int) $data['employment_type_id'] : null,
            'salary_cents' => (int) $data['salary_cents'],
            'salary_currency' => $data['salary_currency'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], $request->user() ?? throw new \RuntimeException('Unauthenticated.'));

        return response()->json(['data' => $this->transform($offer->load('application.applicant'), detailed: true)], 201);
    }

    public function send(Request $request, Offer $offer): JsonResponse
    {
        $this->authorize('manage', $offer);

        $updated = $this->service->sendOffer($offer, $request->user());

        return response()->json(['data' => $this->transform($updated, detailed: true)]);
    }

    public function respond(Request $request, Offer $offer): JsonResponse
    {
        $this->authorize('manage', $offer);

        $data = $request->validate([
            'accepted' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->service->respondToOffer($offer, (bool) $data['accepted'], $data['note'] ?? null, $request->user());

        return response()->json(['data' => $this->transform($updated->load('application'), detailed: true)]);
    }

    public function rescind(Offer $offer): JsonResponse
    {
        $this->authorize('manage', $offer);

        $updated = $this->service->rescindOffer($offer);

        return response()->json(['data' => $this->transform($updated, detailed: true)]);
    }

    public function convert(Request $request, Offer $offer, ConvertOfferToEmployee $action): JsonResponse
    {
        $this->authorize('manage', $offer);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', Tenancy::id())],
            'manager_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('tenant_id', Tenancy::id())],
            'hire_date' => ['nullable', 'date'],
        ]);

        $employee = $action->handle($offer, array_filter([
            'branch_id' => isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            'manager_id' => isset($data['manager_id']) ? (int) $data['manager_id'] : null,
            'hire_date' => $data['hire_date'] ?? null,
        ], static fn ($v): bool => $v !== null));

        return response()->json(['data' => [
            'offer' => $this->transform($offer->fresh() ?? $offer),
            'employee' => [
                'uuid' => $employee->uuid,
                'employee_number' => $employee->employee_number,
                'name' => $employee->fullName(),
            ],
        ]], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Offer $offer, bool $detailed = false): array
    {
        $base = [
            'uuid' => $offer->uuid,
            'status' => $offer->status->value,
            'job_title' => $offer->job_title,
            'salary_cents' => $offer->salary_cents,
            'salary_currency' => $offer->salary_currency,
            'applicant' => $offer->relationLoaded('application') && $offer->application->relationLoaded('applicant')
                ? $offer->application->applicant->fullName()
                : null,
        ];

        if (! $detailed) {
            return $base;
        }

        return $base + [
            'start_date' => $offer->start_date?->toDateString(),
            'expires_on' => $offer->expires_on?->toDateString(),
            'notes' => $offer->notes,
            'sent_at' => $offer->sent_at?->toIso8601String(),
            'responded_at' => $offer->responded_at?->toIso8601String(),
            'converted_employee_id' => $offer->converted_employee_id,
        ];
    }
}
