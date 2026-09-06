<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Enums\PaymentStatus;
use App\Enums\PlatformPermission;
use App\Enums\TenantStatus;
use App\Facades\Tenancy;
use App\Livewire\Concerns\BindsTenant;
use App\Livewire\Platform\Concerns\WithPlatformGuard;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auditor;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TenantDetail extends Component
{
    use BindsTenant;
    use WithPlatformGuard;

    #[Locked]
    public Tenant $tenant;

    public ?string $notice = null;

    public function mount(Tenant $tenant): void
    {
        $this->authorizePlatform(PlatformPermission::TenantsView);
        $this->tenant = $tenant;
    }

    public function suspend(Auditor $auditor): void
    {
        $this->changeStatus(TenantStatus::Suspended, 'platform.tenant.suspended', $auditor);
    }

    public function activate(Auditor $auditor): void
    {
        $this->changeStatus(TenantStatus::Active, 'platform.tenant.activated', $auditor);
    }

    private function changeStatus(TenantStatus $status, string $event, Auditor $auditor): void
    {
        $this->authorizePlatform(PlatformPermission::TenantsManage);

        $from = $this->tenant->status;
        $this->tenant->update(['status' => $status]);

        Tenancy::run($this->tenant, fn () => $auditor->log($event, $this->tenant, [
            'tenant_id' => $this->tenant->getKey(),
            'from' => $from->value,
            'to' => $status->value,
        ]));

        $this->tenant->refresh();
        $this->notice = __(':name is now :state.', ['name' => $this->tenant->name, 'state' => $status->label()]);
    }

    public function render(): View
    {
        $user = auth()->user();
        $canViewPayments = $user !== null && $user->canOnPlatform(PlatformPermission::PaymentsView);
        $tenantId = $this->tenant->getKey();

        $this->tenant->loadMissing(['subscription.plan', 'branding']);

        return view('livewire.platform.tenant-detail', [
            'employeeCount' => Employee::query()->where('tenant_id', $tenantId)->count(),
            'users' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->limit(50)->get(),
            'recentAudit' => AuditLog::query()->where('tenant_id', $tenantId)->latest('id')->limit(20)->get(),
            'canImpersonate' => $user !== null && $user->canOnPlatform(PlatformPermission::TenantsImpersonate),
            'canManage' => $user !== null && $user->canOnPlatform(PlatformPermission::TenantsManage),
            'canViewPayments' => $canViewPayments,
            'payments' => $canViewPayments
                ? Payment::query()->where('tenant_id', $tenantId)->with('invoice:id,number')->latest('id')->limit(15)->get()
                : collect(),
            'invoices' => $canViewPayments
                ? Invoice::query()->where('tenant_id', $tenantId)->latest('id')->limit(15)->get()
                : collect(),
            'paidToDateCents' => $canViewPayments
                ? (int) Payment::query()->where('tenant_id', $tenantId)->where('status', PaymentStatus::Succeeded)->sum('amount_cents')
                : 0,
        ]);
    }
}
