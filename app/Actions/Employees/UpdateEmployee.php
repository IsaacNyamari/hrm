<?php

declare(strict_types=1);

namespace App\Actions\Employees;

use App\Events\EmployeeUpdated;
use App\Models\Employee;
use App\Support\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateEmployee
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Employee $employee, array $attributes): Employee
    {
        return $this->context->run($employee->tenant, fn (): Employee => DB::transaction(function () use ($employee, $attributes): Employee {
            $employee->fill(Arr::except($attributes, ['emergency_contacts', 'bank_accounts', 'statutory']));
            $changes = $employee->getDirty();
            $employee->save();

            if (array_key_exists('statutory', $attributes)) {
                $employee->statutoryDetail()->updateOrCreate([], (array) $attributes['statutory']);
            }

            if (array_key_exists('emergency_contacts', $attributes)) {
                $employee->emergencyContacts()->delete();
                foreach ((array) $attributes['emergency_contacts'] as $contact) {
                    $employee->emergencyContacts()->create($contact);
                }
            }

            if (array_key_exists('bank_accounts', $attributes)) {
                $employee->bankAccounts()->delete();
                foreach ((array) $attributes['bank_accounts'] as $account) {
                    $employee->bankAccounts()->create($account);
                }
            }

            EmployeeUpdated::dispatch($employee, $changes);

            return $employee->refresh();
        }));
    }
}
