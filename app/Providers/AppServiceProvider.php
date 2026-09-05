<?php

namespace App\Providers;

use App\Listeners\RecordLoginActivity;
use App\Listeners\SendBillingNotifications;
use App\Listeners\SendLeaveNotifications;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\TaxRule;
use App\Policies\PayrollPolicy;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Event::subscribe(RecordLoginActivity::class);
        Event::subscribe(SendBillingNotifications::class);
        Event::subscribe(SendLeaveNotifications::class);

        // One policy class serves the whole payroll module.
        foreach ([PayrollPeriod::class, PayrollRun::class, Payslip::class, SalaryComponent::class, SalaryStructure::class, TaxRule::class] as $model) {
            Gate::policy($model, PayrollPolicy::class);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
