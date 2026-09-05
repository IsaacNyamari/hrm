<?php

use App\Exceptions\AttendanceException;
use App\Exceptions\LeaveException;
use App\Exceptions\PayrollException;
use App\Exceptions\PlanLimitExceededException;
use App\Http\Middleware\BindTenantContext;
use App\Http\Middleware\EnsureTenantSubscribed;
use App\Http\Middleware\EnsureUserBelongsToTenant;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'tenant.user' => EnsureUserBelongsToTenant::class,
            'subscription' => EnsureTenantSubscribed::class,
        ]);

        // Soft-bind the tenant for every web request (incl. Livewire's own
        // update route, which sits outside the strict `tenant` group). Never
        // aborts; the `tenant` alias remains the security boundary.
        $middleware->web(append: [BindTenantContext::class]);

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

        // Attendance / leave / payroll rule violations -> 422.
        $exceptions->render(function (AttendanceException|LeaveException|PayrollException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        });
    })->create();
