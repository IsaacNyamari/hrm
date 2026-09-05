<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InterviewMode;
use App\Enums\InterviewRecommendation;
use App\Enums\InterviewStatus;
use App\Facades\Tenancy;
use App\Models\Application;
use App\Models\Interview;
use App\Services\Recruitment\RecruitmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class InterviewController extends Controller
{
    public function __construct(private readonly RecruitmentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $canViewAll = $user?->can('recruitment.view') || $user?->can('recruitment.manage');

        $interviews = Interview::query()
            ->with(['application.applicant:id,first_name,last_name', 'application.vacancy:id,title', 'interviewer:id,name'])
            ->when(! $canViewAll, fn ($q) => $q->where('interviewer_id', $user?->getKey()))
            ->when($request->filled('application_uuid'), fn ($q) => $q->whereRelation('application', 'uuid', $request->string('application_uuid')->toString()))
            ->when($request->boolean('upcoming'), fn ($q) => $q->where('scheduled_at', '>=', now())->where('status', InterviewStatus::Scheduled->value))
            ->orderBy('scheduled_at')
            ->get();

        return response()->json(['data' => $interviews->map(fn (Interview $i): array => $this->transform($i))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'application_uuid' => ['required', Rule::exists('applications', 'uuid')->where('tenant_id', Tenancy::id())],
            'round' => ['nullable', 'integer', 'between:1,20'],
            'mode' => ['sometimes', new Enum(InterviewMode::class)],
            'interviewer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', Tenancy::id())],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'between:5,480'],
            'location' => ['nullable', 'string', 'max:200'],
            'meeting_url' => ['nullable', 'url', 'max:255'],
        ]);

        $application = Application::query()->where('uuid', $data['application_uuid'])->firstOrFail();
        $this->authorize('manage', $application);

        $interview = $this->service->scheduleInterview($application, [
            'round' => isset($data['round']) ? (int) $data['round'] : null,
            'mode' => $data['mode'] ?? InterviewMode::Video->value,
            'interviewer_id' => isset($data['interviewer_id']) ? (int) $data['interviewer_id'] : null,
            'scheduled_at' => (string) $data['scheduled_at'],
            'duration_minutes' => isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : 45,
            'location' => $data['location'] ?? null,
            'meeting_url' => $data['meeting_url'] ?? null,
        ], $request->user());

        return response()->json(['data' => $this->transform($interview->load(['application.applicant', 'interviewer']))], 201);
    }

    public function show(Interview $interview): JsonResponse
    {
        $this->authorize('view', $interview);

        return response()->json(['data' => $this->transform($interview->load(['application.applicant', 'application.vacancy', 'interviewer']), detailed: true)]);
    }

    public function feedback(Request $request, Interview $interview): JsonResponse
    {
        $this->authorize('submitFeedback', $interview);

        $data = $request->validate([
            'status' => ['sometimes', 'in:completed,no_show'],
            'recommendation' => ['nullable', new Enum(InterviewRecommendation::class)],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'feedback' => ['nullable', 'string', 'max:10000'],
        ]);

        $updated = $this->service->recordInterviewFeedback($interview, [
            'status' => $data['status'] ?? InterviewStatus::Completed->value,
            'recommendation' => $data['recommendation'] ?? null,
            'rating' => isset($data['rating']) ? (int) $data['rating'] : null,
            'feedback' => $data['feedback'] ?? null,
        ]);

        return response()->json(['data' => $this->transform($updated, detailed: true)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Interview $interview, bool $detailed = false): array
    {
        $base = [
            'uuid' => $interview->uuid,
            'round' => $interview->round,
            'mode' => $interview->mode->value,
            'status' => $interview->status->value,
            'scheduled_at' => $interview->scheduled_at->toIso8601String(),
            'duration_minutes' => $interview->duration_minutes,
            'interviewer' => $interview->relationLoaded('interviewer') ? $interview->interviewer?->name : null,
            'applicant' => $interview->relationLoaded('application') && $interview->application->relationLoaded('applicant')
                ? $interview->application->applicant->fullName()
                : null,
        ];

        if (! $detailed) {
            return $base;
        }

        return $base + [
            'location' => $interview->location,
            'meeting_url' => $interview->meeting_url,
            'recommendation' => $interview->recommendation?->value,
            'rating' => $interview->rating,
            'feedback' => $interview->feedback,
            'feedback_submitted_at' => $interview->feedback_submitted_at?->toIso8601String(),
        ];
    }
}
