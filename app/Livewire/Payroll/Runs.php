<?php

declare(strict_types=1);

namespace App\Livewire\Payroll;

use App\Exceptions\PayrollException;
use App\Livewire\Concerns\BindsTenant;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
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

    public function render(): View
    {
        return view('livewire.payroll.runs', [
            'periods' => PayrollPeriod::query()->with('runs')->orderByDesc('start_date')->get(),
        ]);
    }
}
