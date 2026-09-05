<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\VacancyStatus;
use App\Facades\Tenancy;
use App\Models\JobPosition;
use App\Models\Vacancy;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VacancyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vacancy::class);

        $vacancies = Vacancy::query()
            ->withCount(['applications', 'applications as active_applications_count' => fn ($q) => $q->where('status', 'active')])
            ->with('department:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $vacancies->map(fn (Vacancy $v): array => $this->transform($v))]);
    }

    public function show(Vacancy $vacancy): JsonResponse
    {
        $this->authorize('view', $vacancy);

        $vacancy->loadCount(['applications', 'applications as active_applications_count' => fn ($q) => $q->where('status', 'active')]);

        return response()->json(['data' => $this->transform($vacancy, detailed: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Vacancy::class);

        $data = $this->validated($request);
        $position = isset($data['job_position_uuid'])
            ? JobPosition::query()->where('uuid', $data['job_position_uuid'])->first()
            : null;

        $title = (string) $data['title'];

        $vacancy = Vacancy::query()->create([
            'job_position_id' => $position?->getKey(),
            'title' => $title,
            'slug' => $this->uniqueSlug($title),
            'department_id' => $data['department_id'] ?? $position?->department_id,
            'designation_id' => $data['designation_id'] ?? $position?->designation_id,
            'branch_id' => $data['branch_id'] ?? null,
            'employment_type_id' => $data['employment_type_id'] ?? $position?->employment_type_id,
            'hiring_manager_id' => $data['hiring_manager_id'] ?? null,
            'opened_by' => $request->user()?->getKey(),
            'headcount' => (int) ($data['headcount'] ?? 1),
            'status' => VacancyStatus::Draft,
            'description' => $data['description'] ?? $position?->description,
            'requirements' => $data['requirements'] ?? $position?->requirements,
            'location' => $data['location'] ?? null,
            'is_remote' => (bool) ($data['is_remote'] ?? false),
            'min_salary_cents' => $data['min_salary_cents'] ?? null,
            'max_salary_cents' => $data['max_salary_cents'] ?? null,
            'salary_currency' => $data['salary_currency'] ?? Tenancy::getOrFail()->currency,
            'pipeline_stages' => $this->pipeline($data['pipeline_stages'] ?? null),
            'closes_on' => $data['closes_on'] ?? null,
        ]);

        return response()->json(['data' => $this->transform($vacancy->fresh(), detailed: true)], 201);
    }

    public function update(Request $request, Vacancy $vacancy): JsonResponse
    {
        $this->authorize('update', $vacancy);

        $data = $this->validated($request, $vacancy);
        unset($data['job_position_uuid']);

        if (isset($data['pipeline_stages'])) {
            $data['pipeline_stages'] = $this->pipeline($data['pipeline_stages']);
        }

        $vacancy->update($data);

        return response()->json(['data' => $this->transform($vacancy->fresh(), detailed: true)]);
    }

    public function publish(Vacancy $vacancy): JsonResponse
    {
        $this->authorize('update', $vacancy);

        abort_if($vacancy->status->isTerminal(), 422, 'This vacancy is closed.');

        $vacancy->forceFill([
            'status' => VacancyStatus::Open,
            'published_at' => $vacancy->published_at ?? CarbonImmutable::now(),
        ])->save();

        return response()->json(['data' => $this->transform($vacancy->fresh(), detailed: true)]);
    }

    public function close(Request $request, Vacancy $vacancy): JsonResponse
    {
        $this->authorize('update', $vacancy);

        $status = $request->string('status')->toString() === 'cancelled'
            ? VacancyStatus::Cancelled
            : VacancyStatus::Closed;

        $vacancy->forceFill(['status' => $status])->save();

        return response()->json(['data' => $this->transform($vacancy->fresh(), detailed: true)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Vacancy $vacancy = null): array
    {
        $required = $vacancy === null ? 'required' : 'sometimes';
        $tenantId = Tenancy::id();

        return $request->validate([
            'title' => [$required, 'string', 'max:150'],
            'job_position_uuid' => ['nullable', Rule::exists('job_positions', 'uuid')->where('tenant_id', $tenantId)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'designation_id' => ['nullable', 'integer', Rule::exists('designations', 'id')->where('tenant_id', $tenantId)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'employment_type_id' => ['nullable', 'integer', 'exists:employment_types,id'],
            'hiring_manager_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'headcount' => ['sometimes', 'integer', 'between:1,999'],
            'description' => ['nullable', 'string', 'max:20000'],
            'requirements' => ['nullable', 'string', 'max:20000'],
            'location' => ['nullable', 'string', 'max:150'],
            'is_remote' => ['boolean'],
            'min_salary_cents' => ['nullable', 'integer', 'min:0'],
            'max_salary_cents' => ['nullable', 'integer', 'min:0', 'gte:min_salary_cents'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'pipeline_stages' => ['nullable', 'array', 'min:2'],
            'pipeline_stages.*' => ['string', 'max:40'],
            'closes_on' => ['nullable', 'date'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function pipeline(mixed $stages): array
    {
        if (! is_array($stages) || $stages === []) {
            /** @var array<int, string> $default */
            $default = config('recruitment.default_pipeline', ['applied', 'screening', 'interview', 'offer', 'hired']);

            return $default;
        }

        return array_values(array_map(fn (mixed $s): string => Str::slug((string) $s, '_'), $stages));
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $n = 2;

        while (Vacancy::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Vacancy $vacancy, bool $detailed = false): array
    {
        $base = [
            'uuid' => $vacancy->uuid,
            'title' => $vacancy->title,
            'slug' => $vacancy->slug,
            'status' => $vacancy->status->value,
            'department' => $vacancy->relationLoaded('department') ? $vacancy->department?->name : null,
            'headcount' => $vacancy->headcount,
            'positions_filled' => $vacancy->positions_filled,
            'applications_count' => $vacancy->applications_count ?? null,
            'active_applications_count' => $vacancy->active_applications_count ?? null,
        ];

        if (! $detailed) {
            return $base;
        }

        return $base + [
            'description' => $vacancy->description,
            'requirements' => $vacancy->requirements,
            'location' => $vacancy->location,
            'is_remote' => $vacancy->is_remote,
            'min_salary_cents' => $vacancy->min_salary_cents,
            'max_salary_cents' => $vacancy->max_salary_cents,
            'salary_currency' => $vacancy->salary_currency,
            'pipeline_stages' => $vacancy->stages(),
            'published_at' => $vacancy->published_at?->toIso8601String(),
            'closes_on' => $vacancy->closes_on?->toDateString(),
        ];
    }
}
