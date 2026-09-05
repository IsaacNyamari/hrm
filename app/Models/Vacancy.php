<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\VacancyStatus;
use Carbon\CarbonImmutable;
use Database\Factories\VacancyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int|null $job_position_id
 * @property string $title
 * @property string $slug
 * @property int|null $department_id
 * @property int|null $designation_id
 * @property int|null $branch_id
 * @property int|null $employment_type_id
 * @property int|null $hiring_manager_id
 * @property int|null $opened_by
 * @property int $headcount
 * @property int $positions_filled
 * @property VacancyStatus $status
 * @property string|null $description
 * @property string|null $requirements
 * @property string|null $location
 * @property bool $is_remote
 * @property int|null $min_salary_cents
 * @property int|null $max_salary_cents
 * @property string|null $salary_currency
 * @property array<int, string> $pipeline_stages
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $closes_on
 * @property-read Department|null $department
 */
class Vacancy extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<VacancyFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VacancyStatus::class,
            'headcount' => 'integer',
            'positions_filled' => 'integer',
            'is_remote' => 'boolean',
            'min_salary_cents' => 'integer',
            'max_salary_cents' => 'integer',
            'pipeline_stages' => 'array',
            'published_at' => 'immutable_datetime',
            'closes_on' => 'immutable_date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<int, string>
     */
    public function stages(): array
    {
        return array_values(array_map('strval', $this->pipeline_stages));
    }

    public function firstStage(): string
    {
        return $this->stages()[0] ?? 'applied';
    }

    public function finalStage(): string
    {
        $stages = $this->stages();

        return $stages[count($stages) - 1] ?? 'hired';
    }

    public function remainingHeadcount(): int
    {
        return max(0, $this->headcount - $this->positions_filled);
    }

    /**
     * @return BelongsTo<JobPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class, 'job_position_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hiring_manager_id');
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
