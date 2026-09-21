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
 * @property string $tracking_code what the candidate uses on the public tracking page
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

    /** Unambiguous characters only (no 0/O, 1/I/L), so codes can be read out and typed. */
    private const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    protected static function booted(): void
    {
        static::creating(function (Application $application): void {
            $application->tracking_code ??= self::newTrackingCode();
        });
    }

    public static function newTrackingCode(): string
    {
        do {
            $code = 'APP-';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (static::query()->withoutGlobalScopes()->where('tracking_code', $code)->exists());

        return $code;
    }

    /**
     * Find an application for the public tracking page. Both the code and the
     * applicant's email must match, so a leaked code alone reveals nothing.
     */
    public static function findForTracking(string $code, string $email): ?self
    {
        return static::query()->withoutGlobalScopes()
            ->where('tracking_code', strtoupper(trim($code)))
            ->whereHas('applicant', fn ($q) => $q->withoutGlobalScopes()->where('email', mb_strtolower(trim($email))))
            ->first();
    }

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
