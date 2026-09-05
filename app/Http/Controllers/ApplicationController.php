<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApplicantSource;
use App\Facades\Tenancy;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Employee;
use App\Models\Vacancy;
use App\Services\Recruitment\RecruitmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    public function __construct(private readonly RecruitmentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Application::class);

        $user = $request->user();
        $canViewAll = $user?->can('recruitment.view') || $user?->can('recruitment.manage');

        $applications = Application::query()
            ->with(['applicant:id,first_name,last_name,email', 'vacancy:id,title,slug'])
            ->when($request->filled('vacancy_uuid'), fn ($q) => $q->whereRelation('vacancy', 'uuid', $request->string('vacancy_uuid')->toString()))
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->string('stage')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when(! $canViewAll, function ($q) use ($user): void {
                $q->where(fn ($inner) => $inner->where('assigned_to', $user?->getKey())
                    ->orWhereHas('interviews', fn ($i) => $i->where('interviewer_id', $user?->getKey())));
            })
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $applications->map(fn (Application $a): array => $this->transform($a))]);
    }

    public function show(Application $application): JsonResponse
    {
        $this->authorize('view', $application);

        $application->load([
            'applicant', 'vacancy:id,title,slug,pipeline_stages',
            'activities.performer:id,name', 'interviews.interviewer:id,name', 'offers',
        ]);

        return response()->json(['data' => $this->transform($application, detailed: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Application::class);

        $data = $request->validate([
            'vacancy_uuid' => ['required', Rule::exists('vacancies', 'uuid')->where('tenant_id', Tenancy::id())],
            'applicant_uuid' => ['required_without:email', Rule::exists('applicants', 'uuid')->where('tenant_id', Tenancy::id())],
            'first_name' => ['required_without:applicant_uuid', 'string', 'max:100'],
            'last_name' => ['required_without:applicant_uuid', 'string', 'max:100'],
            'email' => ['required_without:applicant_uuid', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'cover_letter' => ['nullable', 'string', 'max:20000'],
            'expected_salary_cents' => ['nullable', 'integer', 'min:0'],
            'source' => ['sometimes', 'string'],
        ]);

        $vacancy = Vacancy::query()->where('uuid', $data['vacancy_uuid'])->firstOrFail();

        if (isset($data['applicant_uuid'])) {
            $applicant = Applicant::query()->where('uuid', $data['applicant_uuid'])->firstOrFail();
            $applicantData = [
                'first_name' => $applicant->first_name,
                'last_name' => $applicant->last_name,
                'email' => $applicant->email,
            ];
        } else {
            $applicantData = [
                'first_name' => (string) $data['first_name'],
                'last_name' => (string) $data['last_name'],
                'email' => (string) $data['email'],
                'phone' => $data['phone'] ?? null,
            ];
        }

        $application = $this->service->apply($vacancy, $applicantData, [
            'cover_letter' => $data['cover_letter'] ?? null,
            'expected_salary_cents' => $data['expected_salary_cents'] ?? null,
            'source' => $data['source'] ?? ApplicantSource::Other->value,
        ], $request->user());

        return response()->json(['data' => $this->transform($application->load(['applicant', 'vacancy']), detailed: true)], 201);
    }

    public function moveStage(Request $request, Application $application): JsonResponse
    {
        $this->authorize('manage', $application);

        $stage = $request->validate(['stage' => ['required', 'string']])['stage'];

        $updated = $this->service->moveStage($application, (string) $stage, $request->user());

        return response()->json(['data' => $this->transform($updated->load(['applicant', 'vacancy']))]);
    }

    public function reject(Request $request, Application $application): JsonResponse
    {
        $this->authorize('manage', $application);

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        $updated = $this->service->reject($application, $reason, $request->user());

        return response()->json(['data' => $this->transform($updated->load(['applicant', 'vacancy']))]);
    }

    public function rate(Request $request, Application $application): JsonResponse
    {
        $this->authorize('manage', $application);

        $rating = $request->validate(['rating' => ['nullable', 'integer', 'between:1,5']])['rating'] ?? null;

        $updated = $this->service->rate($application, $rating === null ? null : (int) $rating, $request->user());

        return response()->json(['data' => $this->transform($updated->load(['applicant', 'vacancy']))]);
    }

    public function withdraw(Request $request, Application $application): JsonResponse
    {
        $this->authorize('manage', $application);

        $updated = $this->service->withdraw($application, $request->user());

        return response()->json(['data' => $this->transform($updated->load(['applicant', 'vacancy']))]);
    }

    public function assign(Request $request, Application $application): JsonResponse
    {
        $this->authorize('manage', $application);

        $data = $request->validate([
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('tenant_id', Tenancy::id())],
        ]);

        $userId = null;
        if (isset($data['employee_id'])) {
            $userId = Employee::query()->whereKey($data['employee_id'])->value('user_id');
        }

        $application->forceFill(['assigned_to' => $userId])->save();

        return response()->json(['data' => $this->transform($application->load(['applicant', 'vacancy']))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Application $application, bool $detailed = false): array
    {
        $base = [
            'uuid' => $application->uuid,
            'stage' => $application->stage,
            'status' => $application->status->value,
            'rating' => $application->rating,
            'applicant' => $application->relationLoaded('applicant') ? $application->applicant->fullName() : null,
            'vacancy' => $application->relationLoaded('vacancy') ? $application->vacancy->title : null,
            'applied_at' => $application->applied_at->toIso8601String(),
        ];

        if (! $detailed) {
            return $base;
        }

        return $base + [
            'cover_letter' => $application->cover_letter,
            'expected_salary_cents' => $application->expected_salary_cents,
            'rejected_reason' => $application->rejected_reason,
            'pipeline_stages' => $application->relationLoaded('vacancy') ? $application->vacancy->stages() : [],
            'activities' => $application->relationLoaded('activities')
                ? $application->activities->map(fn ($a): array => [
                    'type' => $a->type,
                    'from_stage' => $a->from_stage,
                    'to_stage' => $a->to_stage,
                    'note' => $a->note,
                    'by' => $a->relationLoaded('performer') ? $a->performer?->name : null,
                    'at' => $a->created_at?->toIso8601String(),
                ])->all()
                : [],
            'interviews' => $application->relationLoaded('interviews')
                ? $application->interviews->map(fn ($i): array => [
                    'uuid' => $i->uuid,
                    'round' => $i->round,
                    'mode' => $i->mode->value,
                    'status' => $i->status->value,
                    'scheduled_at' => $i->scheduled_at->toIso8601String(),
                    'interviewer' => $i->relationLoaded('interviewer') ? $i->interviewer?->name : null,
                    'recommendation' => $i->recommendation?->value,
                ])->all()
                : [],
            'offers' => $application->relationLoaded('offers')
                ? $application->offers->map(fn ($o): array => [
                    'uuid' => $o->uuid,
                    'status' => $o->status->value,
                    'salary_cents' => $o->salary_cents,
                ])->all()
                : [],
        ];
    }
}
