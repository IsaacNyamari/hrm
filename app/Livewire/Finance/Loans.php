<?php

declare(strict_types=1);

namespace App\Livewire\Finance;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Loan;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Loans extends Component
{
    use BindsTenant;

    public string $type = 'salary_advance';

    public string $amount = '';

    public string $installments = '3';

    public ?string $notice = null;

    public ?string $error = null;

    public function request(): void
    {
        $this->reset(['notice', 'error']);
        $this->validate([
            'type' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1'],
            'installments' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $employee = auth()->user()?->employee;
        if ($employee === null) {
            $this->error = 'Your account is not linked to an employee record.';

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

        $this->reset(['amount']);
        $this->notice = 'Loan request submitted for approval.';
    }

    public function render(): View
    {
        $user = auth()->user();
        $employee = $user?->employee;
        $canOversee = $user?->can('payroll.approve') || $user?->can('payroll.manage');

        $mine = $employee !== null
            ? $employee->loans()->with('repayments')->latest('id')->get()
            : collect();

        $pending = $canOversee
            ? Loan::query()->where('status', LoanStatus::Pending->value)->with('employee:id,first_name,last_name')->latest('id')->get()
            : collect();

        return view('livewire.finance.loans', [
            'hasEmployee' => $employee !== null,
            'types' => LoanType::cases(),
            'mine' => $mine,
            'pending' => $pending,
        ]);
    }
}
