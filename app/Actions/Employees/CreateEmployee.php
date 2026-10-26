<?php

declare(strict_types=1);

namespace App\Actions\Employees;

use App\Events\EmployeeCreated;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Employee;
use App\Models\Tenant;
use App\Services\Billing\FeatureGate;
use App\Support\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateEmployee
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly GenerateEmployeeNumber $numbers,
        private readonly FeatureGate $features,
        private readonly CreateStaffAccount $createAccount,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws PlanLimitExceededException
     */
    public function handle(Tenant $tenant, array $attributes): Employee
    {
        return $this->context->run($tenant, function () use ($tenant, $attributes): Employee {
            $this->assertWithinPlanLimit($tenant);

            $employee = DB::transaction(function () use ($tenant, $attributes): Employee {
                $core = Arr::except($attributes, ['emergency_contacts', 'bank_accounts', 'statutory']);
                $core['employee_number'] ??= $this->numbers->forTenant($tenant);

                /** @var Employee $employee */
                $employee = Employee::query()->create($core);

                foreach (Arr::get($attributes, 'emergency_contacts', []) as $contact) {
                    $employee->emergencyContacts()->create($contact);
                }

                foreach (Arr::get($attributes, 'bank_accounts', []) as $account) {
                    $employee->bankAccounts()->create($account);
                }

                if (! empty($attributes['statutory'])) {
                    $employee->statutoryDetail()->create($attributes['statutory']);
                }

                EmployeeCreated::dispatch($employee);

                return $employee->refresh();
            });

            $this->createAccount->handle($employee);

            return $employee->refresh();
        });
    }

    private function assertWithinPlanLimit(Tenant $tenant): void
    {
        $current = Employee::query()->active()->count();

        if (! $this->features->withinLimit($tenant, 'employees', $current)) {
            throw PlanLimitExceededException::for('employees');
        }
    }
}
