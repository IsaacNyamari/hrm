<?php

use App\Exceptions\AssetException;
use App\Exceptions\AttendanceException;
use App\Exceptions\ExpenseException;
use App\Exceptions\LeaveException;
use App\Exceptions\PayrollException;
use App\Exceptions\PerformanceException;
use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\RecruitmentException;
use App\Http\Middleware\BindTenantContext;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTenantSubscribed;
use App\Http\Middleware\EnsureUserBelongsToTenant;
use App\Http\Middleware\ResolveApiTenant;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'tenant.user' => EnsureUserBelongsToTenant::class,
            'platform' => EnsurePlatformAdmin::class,
            'subscription' => EnsureTenantSubscribed::class,
            'api.tenant' => ResolveApiTenant::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        // Soft-bind the tenant for every web request (incl. Livewire's own
        // update route, which sits outside the strict `tenant` group). Never
        // aborts; the `tenant` alias remains the security boundary.
        $middleware->web(append: [BindTenantContext::class, SecurityHeaders::class]);

        // Resolve the tenant before route-model binding so implicit bindings on
        // tenant-owned models are constrained by TenantScope (cross-tenant ids
        // then 404 instead of leaking).
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Plan limits are a business condition, not a server fault.
        $exceptions->render(function (PlanLimitExceededException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], Response::HTTP_PAYMENT_REQUIRED);
            }

            return response($e->getMessage(), Response::HTTP_PAYMENT_REQUIRED);
        });

        // Attendance / leave / payroll / performance / recruitment / expense / asset rule violations -> 422.
        $exceptions->render(function (AssetException|AttendanceException|ExpenseException|LeaveException|PayrollException|PerformanceException|RecruitmentException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        });
    })->create();
