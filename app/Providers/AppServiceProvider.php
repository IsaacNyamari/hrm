<?php

namespace App\Providers;

use App\Listeners\ApplyTenantMailBranding;
use App\Listeners\DispatchWebhooks;
use App\Listeners\RecordAuditTrail;
use App\Listeners\RecordLoginActivity;
use App\Listeners\SendBillingNotifications;
use App\Listeners\SendExpenseNotifications;
use App\Listeners\SendLeaveNotifications;
use App\Listeners\SendPerformanceNotifications;
use App\Listeners\SendPortalNotifications;
use App\Listeners\SendRecruitmentNotifications;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\TaxRule;
use App\Policies\PayrollPolicy;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
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
        Event::subscribe(SendPortalNotifications::class);
        Event::subscribe(SendPerformanceNotifications::class);
        Event::subscribe(SendRecruitmentNotifications::class);
        Event::subscribe(SendExpenseNotifications::class);
        Event::subscribe(DispatchWebhooks::class);
        Event::subscribe(RecordAuditTrail::class);

        // Per-tenant e-mail From / Reply-To.
        Event::listen(MessageSending::class, [ApplyTenantMailBranding::class, 'handle']);

        // Expose the bound tenant's branding to the markdown mail components.
        View::composer(['mail::header', 'mail::footer', 'mail::layout', 'mail::message'], function ($view): void {
            $tenant = app(TenantContext::class)->get();
            $view->with('tenantBrand', $tenant?->brandingOrNew());
        });

        // One policy class serves the whole payroll module.
        foreach ([PayrollPeriod::class, PayrollRun::class, Payslip::class, SalaryComponent::class, SalaryStructure::class, TaxRule::class] as $model) {
            Gate::policy($model, PayrollPolicy::class);
        }

        // API v1 rate limit — per token, falling back to the client IP.
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
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
