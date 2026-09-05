<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use Carbon\CarbonImmutable;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int|null $user_id
 * @property string $employee_number
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $preferred_name
 * @property string|null $email
 * @property string|null $personal_email
 * @property string|null $phone
 * @property string|null $alternate_phone
 * @property CarbonImmutable|null $date_of_birth
 * @property Gender|null $gender
 * @property MaritalStatus|null $marital_status
 * @property string|null $nationality
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property string|null $job_title
 * @property CarbonImmutable|null $hire_date
 * @property CarbonImmutable|null $probation_ends_at
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $exit_date
 * @property EmploymentStatus $status
 * @property int|null $employment_type_id
 * @property int|null $department_id
 * @property int|null $designation_id
 * @property int|null $branch_id
 * @property int|null $manager_id
 * @property int|null $salary_amount_cents
 * @property string|null $salary_currency
 * @property string|null $pay_frequency
 * @property string|null $profile_photo_path
 * @property string|null $notes
 * @property-read Tenant $tenant
 */
class Employee extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EmployeeFactory> */
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
            'date_of_birth' => 'immutable_date',
            'hire_date' => 'immutable_date',
            'probation_ends_at' => 'immutable_date',
            'confirmed_at' => 'immutable_date',
            'exit_date' => 'immutable_date',
            'status' => EmploymentStatus::class,
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'salary_amount_cents' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])));
    }

    public function displayName(): string
    {
        return $this->preferred_name !== null && $this->preferred_name !== ''
            ? "{$this->preferred_name} {$this->last_name}"
            : $this->fullName();
    }

    /* ---------------------------------------------------------------------
     | Relationships
     * ------------------------------------------------------------------- */

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<EmploymentType, $this>
     */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    /**
     * @return HasMany<EmployeeEmergencyContact, $this>
     */
    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmployeeEmergencyContact::class);
    }

    /**
     * @return HasMany<EmployeeBankAccount, $this>
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class);
    }

    /**
     * @return HasOne<EmployeeStatutoryDetail, $this>
     */
    public function statutoryDetail(): HasOne
    {
        return $this->hasOne(EmployeeStatutoryDetail::class);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * @return HasMany<ShiftAssignment, $this>
     */
    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @return HasMany<LeaveBalance, $this>
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * @return HasMany<EmployeeSalaryComponent, $this>
     */
    public function salaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * @return HasMany<EmployeeDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * @return HasMany<HrRequest, $this>
     */
    public function hrRequests(): HasMany
    {
        return $this->hasMany(HrRequest::class);
    }

    /* ---------------------------------------------------------------------
     | Scopes
     * ------------------------------------------------------------------- */

    /**
     * @param  Builder<Employee>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [
            EmploymentStatus::Active->value,
            EmploymentStatus::Probation->value,
            EmploymentStatus::OnLeave->value,
        ]);
    }

    /**
     * @param  Builder<Employee>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where(function (Builder $q) use ($like): void {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('preferred_name', 'like', $like)
                ->orWhere('employee_number', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('job_title', 'like', $like);
        });
    }
}
