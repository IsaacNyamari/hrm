<?php

declare(strict_types=1);

namespace App\Actions\Recruitment;

use App\Actions\Employees\CreateEmployee;
use App\Actions\Employees\CreateStaffAccount;
use App\Enums\EmploymentStatus;
use App\Enums\OfferStatus;
use App\Events\ApplicationProgressUpdated;
use App\Exceptions\RecruitmentException;
use App\Models\Employee;
use App\Models\Offer;
use App\Support\RealTime;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Turns an accepted offer into an active employee record, links it back to the
 * applicant and closes out the application. The new hire also gets a staff
 * login and a welcome email to set their password. Reuses {@see CreateEmployee} so the
 * plan-limit check and employee-number generation still apply.
 */
class ConvertOfferToEmployee
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CreateEmployee $createEmployee,
        private readonly CreateStaffAccount $createAccount,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function handle(Offer $offer, array $overrides = []): Employee
    {
        if ($offer->status !== OfferStatus::Accepted) {
            throw RecruitmentException::offerNotAccepted();
        }

        if ($offer->converted_employee_id !== null) {
            throw RecruitmentException::alreadyConverted();
        }

        $tenant = $offer->application->vacancy->tenant;

        return $this->context->run($tenant, function () use ($offer, $overrides, $tenant): Employee {
            return DB::transaction(function () use ($offer, $overrides, $tenant): Employee {
                $application = $offer->application()->with(['applicant', 'vacancy'])->firstOrFail();
                $applicant = $application->applicant;

                $attributes = array_merge([
                    'first_name' => $applicant->first_name,
                    'last_name' => $applicant->last_name,
                    'email' => $applicant->email,
                    'phone' => $applicant->phone,
                    'job_title' => $offer->job_title,
                    'department_id' => $offer->department_id,
                    'designation_id' => $offer->designation_id,
                    'employment_type_id' => $offer->employment_type_id,
                    'branch_id' => $application->vacancy->branch_id,
                    'manager_id' => $application->vacancy->hiring_manager_id,
                    'status' => EmploymentStatus::Probation,
                    'hire_date' => ($offer->start_date ?? CarbonImmutable::now())->toDateString(),
                    'salary_amount_cents' => $offer->salary_cents,
                    'salary_currency' => $offer->salary_currency,
                    'pay_frequency' => 'monthly',
                ], $overrides);

                $employee = $this->createEmployee->handle($tenant, $attributes);

                $offer->forceFill(['converted_employee_id' => $employee->getKey()])->save();

                $this->createAccount->handle($employee);

                RealTime::send(ApplicationProgressUpdated::for($application));

                return $employee;
            });
        });
    }
}
