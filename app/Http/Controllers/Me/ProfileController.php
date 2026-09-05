<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Events\EmployeeProfileUpdated;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Employee self-service profile — a strictly whitelisted subset of fields the
 * employee may change about themselves. Everything else (name, salary,
 * department, employment status, …) stays HR-controlled.
 */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $employee->load(['emergencyContacts', 'bankAccounts']);

        return response()->json(['data' => [
            'name' => $employee->fullName(),
            'employee_number' => $employee->employee_number,
            'email' => $employee->email,
            'personal_email' => $employee->personal_email,
            'phone' => $employee->phone,
            'alternate_phone' => $employee->alternate_phone,
            'address_line1' => $employee->address_line1,
            'address_line2' => $employee->address_line2,
            'city' => $employee->city,
            'state' => $employee->state,
            'postal_code' => $employee->postal_code,
            'country' => $employee->country,
            'emergency_contacts' => $employee->emergencyContacts->map(fn ($c): array => [
                'name' => $c->name, 'relationship' => $c->relationship, 'phone' => $c->phone, 'is_primary' => $c->is_primary,
            ])->all(),
            'bank_accounts' => $employee->bankAccounts->map(fn ($b): array => [
                'bank_name' => $b->bank_name, 'account_name' => $b->account_name,
                'account_number' => $b->maskedAccountNumber(), 'is_primary' => $b->is_primary,
            ])->all(),
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        $data = $request->validate([
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'emergency_contacts' => ['sometimes', 'array', 'max:5'],
            'emergency_contacts.*.name' => ['required_with:emergency_contacts', 'string', 'max:150'],
            'emergency_contacts.*.relationship' => ['nullable', 'string', 'max:60'],
            'emergency_contacts.*.phone' => ['required_with:emergency_contacts', 'string', 'max:30'],
            'bank_account' => ['sometimes', 'array'],
            'bank_account.bank_name' => ['required_with:bank_account', 'string', 'max:150'],
            'bank_account.account_name' => ['required_with:bank_account', 'string', 'max:150'],
            'bank_account.account_number' => ['required_with:bank_account', 'string', 'max:60'],
        ]);

        $scalar = Arr::only($data, [
            'personal_email', 'phone', 'alternate_phone', 'address_line1', 'address_line2',
            'city', 'state', 'postal_code', 'country',
        ]);

        DB::transaction(function () use ($employee, $data, $scalar): void {
            $employee->fill($scalar);
            $changes = $employee->getDirty();
            $employee->save();

            if (array_key_exists('emergency_contacts', $data)) {
                $employee->emergencyContacts()->delete();
                foreach ($data['emergency_contacts'] as $i => $contact) {
                    $employee->emergencyContacts()->create($contact + ['is_primary' => $i === 0]);
                }
                $changes['emergency_contacts'] = true;
            }

            if (array_key_exists('bank_account', $data)) {
                $employee->bankAccounts()->update(['is_primary' => false]);
                $employee->bankAccounts()->create($data['bank_account'] + ['is_primary' => true]);
                $changes['bank_account'] = true;
            }

            EmployeeProfileUpdated::dispatch($employee, $changes, bySelf: true);
        });

        return $this->show($request);
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->user()?->employee;
        abort_if($employee === null, 422, 'Your account is not linked to an employee record.');

        return $employee;
    }
}
