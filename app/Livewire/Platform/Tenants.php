<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Actions\Tenancy\ChangeTenantStatus;
use App\Enums\PlatformPermission;
use App\Enums\TenantStatus;
use App\Livewire\Concerns\BindsTenant;
use App\Livewire\Platform\Concerns\WithPlatformGuard;
use App\Models\Tenant;
use App\Support\Auditor;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Tenants extends Component
{
    use BindsTenant;
    use WithPagination;
    use WithPlatformGuard;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorizePlatform(PlatformPermission::TenantsView);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function suspend(int $tenantId, Auditor $auditor): void
    {
        $this->changeStatus($tenantId, TenantStatus::Suspended, 'platform.tenant.suspended', $auditor);
    }

    public function activate(int $tenantId, Auditor $auditor): void
    {
        $this->changeStatus($tenantId, TenantStatus::Active, 'platform.tenant.activated', $auditor);
    }

    private function changeStatus(int $tenantId, TenantStatus $status, string $event, Auditor $auditor): void
    {
        $this->authorizePlatform(PlatformPermission::TenantsManage);

        $tenant = app(ChangeTenantStatus::class)->handle(Tenant::query()->findOrFail($tenantId), $status, $event);

        $this->notice = __(':name is now :state.', ['name' => $tenant->name, 'state' => $status->label()]);
    }

    public function render(): View
    {
        $tenants = Tenant::query()
            ->with('subscription.plan:id,name')
            ->withCount('users')
            ->when($this->search !== '', fn ($q) => $q->where(function ($w): void {
                $term = '%'.$this->search.'%';
                $w->where('name', 'like', $term)->orWhere('slug', 'like', $term)->orWhere('email', 'like', $term);
            }))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest('id')
            ->paginate(20);

        return view('livewire.platform.tenants', [
            'tenants' => $tenants,
            'statuses' => TenantStatus::cases(),
            'canManage' => auth()->user()?->canOnPlatform(PlatformPermission::TenantsManage) === true,
        ]);
    }
}
