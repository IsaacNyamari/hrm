<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\Gender;
use App\Enums\LeaveAccrual;
use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $name
 * @property string $code
 * @property float $default_days_per_year
 * @property LeaveAccrual $accrual
 * @property bool $is_paid
 * @property bool $requires_approval
 * @property bool $requires_attachment
 * @property int $min_notice_days
 * @property int|null $max_consecutive_days
 * @property bool $allow_carry_forward
 * @property float|null $max_carry_forward_days
 * @property string|null $gender_restriction
 * @property int|null $employment_type_id
 * @property bool $is_active
 */
class LeaveType extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accrual' => LeaveAccrual::class,
            'default_days_per_year' => 'float',
            'max_carry_forward_days' => 'float',
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'requires_attachment' => 'boolean',
            'allow_carry_forward' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function allowsGender(?Gender $gender): bool
    {
        if ($this->gender_restriction === null) {
            return true;
        }

        return $gender !== null && $gender->value === $this->gender_restriction;
    }

    /**
     * The seven statutory-agnostic defaults every tenant starts with.
     *
     * @return array<int, array{name: string, code: string, paid: bool, attachment: bool, days: float}>
     */
    public static function defaults(): array
    {
        return [
            ['name' => 'Annual Leave', 'code' => 'ANNUAL', 'paid' => true, 'attachment' => false, 'days' => 21],
            ['name' => 'Sick Leave', 'code' => 'SICK', 'paid' => true, 'attachment' => true, 'days' => 14],
            ['name' => 'Maternity Leave', 'code' => 'MATERNITY', 'paid' => true, 'attachment' => true, 'days' => 90],
            ['name' => 'Paternity Leave', 'code' => 'PATERNITY', 'paid' => true, 'attachment' => false, 'days' => 14],
            ['name' => 'Compassionate Leave', 'code' => 'COMPASSIONATE', 'paid' => true, 'attachment' => false, 'days' => 5],
            ['name' => 'Study Leave', 'code' => 'STUDY', 'paid' => false, 'attachment' => true, 'days' => 10],
            ['name' => 'Unpaid Leave', 'code' => 'UNPAID', 'paid' => false, 'attachment' => false, 'days' => 0],
        ];
    }
}
