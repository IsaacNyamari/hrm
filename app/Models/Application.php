<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\ApplicantSource;
use App\Enums\ApplicationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $vacancy_id
 * @property int $applicant_id
 * @property string $stage
 * @property ApplicationStatus $status
 * @property int|null $rating
 * @property string|null $cover_letter
 * @property int|null $expected_salary_cents
 * @property ApplicantSource $source
 * @property string|null $rejected_reason
 * @property int|null $assigned_to
 * @property CarbonImmutable $applied_at
 * @property CarbonImmutable|null $stage_changed_at
 * @property CarbonImmutable|null $decided_at
 * @property-read Vacancy $vacancy
 * @property-read Applicant $applicant
 */
class Application extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'source' => ApplicantSource::class,
            'rating' => 'integer',
            'expected_salary_cents' => 'integer',
            'applied_at' => 'immutable_datetime',
            'stage_changed_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Vacancy, $this>
     */
    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    /**
     * @return BelongsTo<Applicant, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * @return HasMany<ApplicationActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ApplicationActivity::class)->latest('id');
    }

    /**
     * @return HasMany<Interview, $this>
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class)->orderBy('round');
    }

    /**
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class)->latest('id');
    }
}
