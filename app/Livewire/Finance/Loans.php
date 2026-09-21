<?php

declare(strict_types=1);

namespace App\Livewire\Finance;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Employee;
use App\Models\Loan;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Loans extends Component
{
    use BindsTenant;

    public bool $showForm = false;

    public string $type = 'salary_advance';

    public string $amount = '';

    public string $installments = '3';

    public string $employeeId = '';

    public function startRequest(): void
    {
        $this->reset(['type', 'amount', 'installments', 'employeeId']);
        $this->type = 'salary_advance';
        $this->installments = '3';
        $this->showForm = true;
    }

    public function request(): void
    {
        $this->validate([
            'type' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1'],
            'installments' => ['required', 'integer', 'min:1', 'max:120'],
            'employeeId' => ['nullable', 'integer'],
        ]);

        $user = auth()->user();
        $canOversee = $user?->can('payroll.manage');

        if ($this->employeeId !== '' && $canOversee) {
            $employee = Employee::query()->find((int) $this->employeeId);
        } else {
            $employee = $user?->employee;
        }

        if ($employee === null) {
            Flux::toast(variant: 'danger', text: __('Your account is not linked to an employee record.'));

            return;
        }

        $principal = (int) round(((float) $this->amount) * 100);
        $installments = (int) $this->installments;

        Loan::query()->create([
            'employee_id' => $employee->getKey(),
            'type' => LoanType::from($this->type),
            'principal_cents' => $principal,
            'interest_rate' => 0,
            'installments' => $installments,
            'installment_cents' => (int) ceil($principal / $installments),
            'outstanding_cents' => $principal,
            'start_date' => now()->startOfMonth()->addMonth()->toDateString(),
            'status' => LoanStatus::Pending,
        ]);

        $this->showForm = false;
        Flux::toast(variant: 'success', text: __('Loan request submitted for approval.'));
    }

    public function approve(string $uuid): void
    {
        $loan = Loan::query()->where('uuid', $uuid)->first();

        if ($loan === null) {
            Flux::toast(variant: 'danger', text: __('Loan not found.'));

            return;
        }

        Gate::authorize('approve', $loan);

        if ($loan->status !== LoanStatus::Pending) {
            Flux::toast(variant: 'danger', text: __('This loan is not pending approval.'));

            return;
        }

        $loan->forceFill([
            'status' => LoanStatus::Active,
            'approved_by' => auth()->id(),
            'approved_at' => CarbonImmutable::now(),
        ])->save();

        Flux::toast(variant: 'success', text: __('Loan approved.'));
    }

    public function cancel(string $uuid): void
    {
        $loan = Loan::query()->where('uuid', $uuid)->first();

        if ($loan === null) {
            Flux::toast(variant: 'danger', text: __('Loan not found.'));

            return;
        }

        Gate::authorize('cancel', $loan);

        if ($loan->status !== LoanStatus::Pending) {
            Flux::toast(variant: 'danger', text: __('Only a pending loan can be cancelled.'));

            return;
        }

        $loan->forceFill(['status' => LoanStatus::Cancelled])->save();
        Flux::toast(variant: 'success', text: __('Loan cancelled.'));
    }

    public function remove(string $uuid): void
    {
        $loan = Loan::query()->where('uuid', $uuid)->first();

        if ($loan === null) {
            Flux::toast(variant: 'danger', text: __('Loan not found.'));

            return;
        }

        Gate::authorize('delete', $loan);

        if ($loan->repayments()->exists()) {
            Flux::toast(variant: 'danger', text: __('This loan has repayments and cannot be deleted.'));

            return;
        }

        $loan->delete();
        Flux::modal('delete-loan-'.$uuid)->close();
        Flux::toast(variant: 'success', text: __('Loan deleted.'));
    }

    public function render(): View
    {
        $user = auth()->user();
        $employee = $user?->employee;
        $canOversee = $user?->can('payroll.approve') || $user?->can('payroll.manage');
        $canManage = $user?->can('payroll.manage');

        $mine = $employee !== null
            ? $employee->loans()->with('repayments')->latest('id')->get()
            : collect();

        $pending = $canOversee
            ? Loan::query()->where('status', LoanStatus::Pending->value)->with('employee:id,first_name,last_name')->latest('id')->get()
            : collect();

        $all = $canOversee
            ? Loan::query()->with('employee:id,first_name,last_name')->latest('id')->get()
            : collect();

        return view('livewire.finance.loans', [
            'hasEmployee' => $employee !== null,
            'types' => LoanType::cases(),
            'mine' => $mine,
            'pending' => $pending,
            'all' => $all,
            'canOversee' => $canOversee,
            'canManage' => $canManage,
            'employees' => $canManage ? Employee::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name']) : collect(),
        ]);
    }
}
