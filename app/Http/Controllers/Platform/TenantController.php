<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Enums\PlatformPermission;
use App\Enums\TenantStatus;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->canOnPlatform(PlatformPermission::TenantsView), 403);

        $tenants = Tenant::query()
            ->with('subscription.plan:id,name')
            ->withCount('users')
            ->when(
                $request->filled('q'),
                fn ($q) => $q->where(function ($w) use ($request): void {
                    $term = '%'.$request->string('q')->toString().'%';
                    $w->where('name', 'like', $term)->orWhere('slug', 'like', $term)->orWhere('email', 'like', $term);
                }),
            )
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->toString()),
            )
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('platform.tenants.index', [
            'tenants' => $tenants,
            'statuses' => TenantStatus::cases(),
            'filters' => ['q' => $request->string('q')->toString(), 'status' => $request->string('status')->toString()],
        ]);
    }

    public function show(Request $request, Tenant $tenant): View
    {
        $user = $request->user();
        abort_unless($user !== null && $user->canOnPlatform(PlatformPermission::TenantsView), 403);

        $tenant->load(['subscription.plan', 'branding']);

        return view('platform.tenants.show', [
            'tenant' => $tenant,
            'employeeCount' => Employee::query()->where('tenant_id', $tenant->getKey())->count(),
            'users' => User::query()->where('tenant_id', $tenant->getKey())->orderBy('name')->limit(50)->get(),
            'recentAudit' => AuditLog::query()
                ->where('tenant_id', $tenant->getKey())
                ->latest('id')
                ->limit(20)
                ->get(),
            'canImpersonate' => $user->canOnPlatform(PlatformPermission::TenantsImpersonate),
            'canManage' => $user->canOnPlatform(PlatformPermission::TenantsManage),
        ]);
    }

    public function suspend(Request $request, Tenant $tenant, Auditor $auditor): RedirectResponse
    {
        return $this->transition($request, $tenant, $auditor, TenantStatus::Suspended, 'platform.tenant.suspended');
    }

    public function activate(Request $request, Tenant $tenant, Auditor $auditor): RedirectResponse
    {
        return $this->transition($request, $tenant, $auditor, TenantStatus::Active, 'platform.tenant.activated');
    }

    private function transition(Request $request, Tenant $tenant, Auditor $auditor, TenantStatus $status, string $event): RedirectResponse
    {
        abort_unless($request->user()?->canOnPlatform(PlatformPermission::TenantsManage), 403);

        $from = $tenant->status;
        $tenant->update(['status' => $status]);

        // Attribute the entry to the target organization even though the
        // operator has no tenant of their own.
        Tenancy::run($tenant, fn () => $auditor->log($event, $tenant, [
            'tenant_id' => $tenant->getKey(),
            'from' => $from->value,
            'to' => $status->value,
        ]));

        return redirect()
            ->route('platform.tenants.show', $tenant)
            ->with('status', __(':name is now :state.', ['name' => $tenant->name, 'state' => $status->label()]));
    }
}
