<?php

declare(strict_types=1);

namespace App\Livewire\Payroll;

use App\Actions\Payroll\PrepareFinalSettlement;
use App\Enums\EmploymentStatus;
use App\Exceptions\PayrollException;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Employees who have left, and the final pay each is owed. Preparing a
 * settlement creates an off-cycle payroll run that is then approved and
 * paid from the payroll runs table.
 */
class FinalSettlements extends Component
{
    use BindsTenant;

    public ?string $error = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', PayrollPeriod::class);
    }

    public function prepare(string $employeeUuid, PrepareFinalSettlement $settle): void
    {
        Gate::authorize('create', PayrollPeriod::class);

        $this->error = null;
        $employee = Employee::query()->where('uuid', $employeeUuid)->firstOrFail();

        try {
            $run = $settle->handle($employee, auth()->user());
        } catch (PayrollException $e) {
            $this->error = $e->getMessage();

            return;
        }

        Flux::toast(variant: 'success', text: __('Final pay for :name is ready for approval: :amount net.', [
            'name' => $employee->fullName(),
            'amount' => number_format($run->net_cents / 100, 2),
        ]));

        $this->dispatch('payroll-updated');
    }

    public function render(): View
    {
        $exited = Employee::query()
            ->whereIn('status', [EmploymentStatus::Terminated->value, EmploymentStatus::Resigned->value, EmploymentStatus::Retired->value])
            ->whereNotNull('exit_date')
            ->whereDate('exit_date', '>=', CarbonImmutable::today()->subYear()->toDateString())
            ->orderByDesc('exit_date')
            ->get();

        $settlements = PayrollPeriod::query()
            ->whereIn('employee_id', $exited->modelKeys())
            ->with(['runs' => fn ($runs) => $runs->latest('id')->with('payslips:id,uuid,payroll_run_id,employee_id,net_pay_cents')])
            ->get()
            ->keyBy('employee_id');

        return view('livewire.payroll.final-settlements', [
            'exited' => $exited,
            'settlements' => $settlements,
            'canPrepare' => Gate::allows('create', PayrollPeriod::class),
        ]);
    }
}
