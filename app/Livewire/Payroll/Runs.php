<?php

declare(strict_types=1);

namespace App\Livewire\Payroll;

use App\Enums\PayrollRunStatus;
use App\Exceptions\PayrollException;
use App\Livewire\Concerns\BindsTenant;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\StatutoryRuleWatch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class Runs extends Component
{
    use BindsTenant;

    public ?string $error = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', PayrollPeriod::class);
    }

    public function process(string $periodUuid, PayrollService $service): void
    {
        $this->guarded(function () use ($periodUuid, $service): void {
            $period = PayrollPeriod::query()->where('uuid', $periodUuid)->firstOrFail();
            Gate::authorize('process', $period);
            $service->run($period, [], auth()->user());
        });
    }

    public function approve(string $runUuid, PayrollService $service): void
    {
        $this->guarded(function () use ($runUuid, $service): void {
            $run = PayrollRun::query()->where('uuid', $runUuid)->firstOrFail();
            Gate::authorize('approve', $run);
            $service->approve($run, auth()->user());
        });
    }

    public function pay(string $runUuid, PayrollService $service): void
    {
        $this->guarded(function () use ($runUuid, $service): void {
            $run = PayrollRun::query()->where('uuid', $runUuid)->firstOrFail();
            Gate::authorize('approve', $run);
            $service->markPaid($run);
        });
    }

    public function lock(string $runUuid, PayrollService $service): void
    {
        $this->guarded(function () use ($runUuid, $service): void {
            $run = PayrollRun::query()->where('uuid', $runUuid)->firstOrFail();
            Gate::authorize('approve', $run);
            $service->lock($run);
        });
    }

    private function guarded(callable $action): void
    {
        $this->error = null;

        try {
            $action();
        } catch (PayrollException $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * Re-render when another panel (e.g. a final settlement) changes payroll.
     */
    #[On('payroll-updated')]
    public function refreshRuns(): void {}

    public function render(): View
    {
        $periods = PayrollPeriod::query()->with('runs')->orderByDesc('start_date')->get();

        return view('livewire.payroll.runs', [
            'periods' => $periods,
            'unpaidByRun' => $this->unpaidEmployees($periods),
            'ruleAlerts' => app(StatutoryRuleWatch::class)->alerts(),
        ]);
    }

    /**
     * Employees paid for zero days on attendance-based runs still awaiting
     * approval — usually missing clock-ins rather than a month of absence.
     *
     * @param  Collection<int, PayrollPeriod>  $periods
     * @return array<int, Collection<int, string>>
     */
    private function unpaidEmployees(Collection $periods): array
    {
        $runIds = $periods
            ->map(fn (PayrollPeriod $period) => $period->runs->sortByDesc('id')->first())
            ->filter(fn (?PayrollRun $run) => $run?->status === PayrollRunStatus::Processed)
            ->map->getKey()
            ->values();

        if ($runIds->isEmpty()) {
            return [];
        }

        return Payslip::query()
            ->whereIn('payroll_run_id', $runIds)
            ->with('employee:id,first_name,last_name')
            ->get(['id', 'payroll_run_id', 'employee_id', 'snapshot'])
            ->filter(fn (Payslip $payslip) => ($payslip->snapshot['salary_basis'] ?? null) === 'attendance'
                && (float) ($payslip->snapshot['attendance']['payable_days'] ?? 1) <= 0)
            ->groupBy('payroll_run_id')
            ->map(fn (Collection $slips) => $slips->map(fn (Payslip $p) => $p->employee?->fullName() ?? '—')->values())
            ->all();
    }
}
