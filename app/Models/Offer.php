<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\OfferStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $application_id
 * @property string $job_title
 * @property int|null $department_id
 * @property int|null $designation_id
 * @property int|null $employment_type_id
 * @property int $salary_cents
 * @property string $salary_currency
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $expires_on
 * @property OfferStatus $status
 * @property string|null $notes
 * @property int|null $converted_employee_id
 * @property int|null $approved_by
 * @property int|null $created_by
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $responded_at
 * @property-read Application $application
 */
class Offer extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'salary_cents' => 'integer',
            'start_date' => 'immutable_date',
            'expires_on' => 'immutable_date',
            'sent_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
