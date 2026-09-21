<?php

declare(strict_types=1);

namespace App\Livewire\Payroll;

use App\Enums\Permission;
use App\Facades\Tenancy;
use App\Livewire\Concerns\BindsTenant;
use App\Support\Auditor;
use App\Support\Payroll\PayRules;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * How attendance feeds payroll for this organization: whether basic salary is
 * prorated by attendance, and how overtime is paid.
 */
class PayRulesSettings extends Component
{
    use BindsTenant;

    public string $salary_basis = PayRules::BASIS_ATTENDANCE;

    public bool $overtime_enabled = true;

    public float $overtime_multiplier = 1.5;

    public float $rest_day_multiplier = 2.0;

    public float $standard_daily_hours = 8.0;

    public bool $saved = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can(Permission::PayrollView->value), 403);

        foreach (PayRules::for(Tenancy::getOrFail())->toArray() as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function save(Auditor $auditor): void
    {
        abort_unless(auth()->user()?->can(Permission::PayrollManage->value), 403);

        $validated = $this->validate([
            'salary_basis' => ['required', Rule::in([PayRules::BASIS_ATTENDANCE, PayRules::BASIS_FIXED])],
            'overtime_enabled' => ['boolean'],
            'overtime_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'rest_day_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'standard_daily_hours' => ['required', 'numeric', 'min:1', 'max:24'],
        ]);

        $tenant = Tenancy::getOrFail();
        $tenant->forceFill(['settings' => [...(array) $tenant->settings, 'payroll' => $validated]])->save();

        $auditor->log('payroll.pay_rules_updated', $tenant, $validated);

        $this->saved = true;
    }

    public function render(): View
    {
        return view('livewire.payroll.pay-rules-settings', [
            'canManage' => (bool) auth()->user()?->can(Permission::PayrollManage->value),
        ]);
    }
}
