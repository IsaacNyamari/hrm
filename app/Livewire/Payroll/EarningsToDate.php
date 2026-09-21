<?php

declare(strict_types=1);

namespace App\Livewire\Payroll;

use App\Facades\Tenancy;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use App\Services\Payroll\PayrollService;
use App\Support\Payroll\CurrentPayCycle;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Live view of what every employee has earned so far in the current pay
 * cycle, from attendance up to today. It grows day by day until the run.
 */
class EarningsToDate extends Component
{
    use BindsTenant;

    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', PayrollPeriod::class);
    }

    public function render(PayrollService $payroll): View
    {
        $today = CarbonImmutable::today();
        $period = CurrentPayCycle::for(Tenancy::getOrFail(), $today);
        $components = SalaryComponent::query()->get()->keyBy('code');

        $rows = Employee::query()
            ->active()
            ->search($this->search)
            ->with(['salaryComponents.component', 'loans'])
            ->orderBy('first_name')
            ->get()
            ->map(function (Employee $employee) use ($payroll, $period, $today, $components): array {
                $estimate = $payroll->estimate($employee, $period, $today, $components);

                return [
                    'employee' => $employee,
                    'earned' => $estimate['gross_cents'],
                    'overtime' => $estimate['overtime_cents'],
                    'full' => $estimate['full_basic_cents'],
                    'payable_days' => (float) ($estimate['attendance']['payable_days'] ?? 0),
                    'overtime_hours' => (float) ($estimate['attendance']['overtime_hours'] ?? 0) + (float) ($estimate['attendance']['rest_day_hours'] ?? 0),
                ];
            });

        $elapsed = $today->lessThan($period->start_date) ? 0 : $period->start_date->diffInDays($today->min($period->end_date)) + 1;

        return view('livewire.payroll.earnings-to-date', [
            'period' => $period,
            'rows' => $rows,
            'totalEarned' => $rows->sum('earned'),
            'totalOvertime' => $rows->sum('overtime'),
            'daysElapsed' => (int) $elapsed,
            'daysInCycle' => (int) $period->start_date->diffInDays($period->end_date) + 1,
            'currency' => Tenancy::getOrFail()->currency,
        ]);
    }
}
