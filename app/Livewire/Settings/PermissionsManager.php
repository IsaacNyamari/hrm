<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\Role;
use App\Livewire\Concerns\BindsTenant;
use App\Models\User;
use App\Support\Auditor;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Lets a tenant admin (Tenant Owner / HR Administrator, via the
 * `roles.manage` permission) assign a role to the people under them. Granting
 * or changing the Tenant Owner role itself is reserved to the platform admin
 * (see App\Livewire\Platform\TenantDetail) — it never appears in the role
 * list here, and this page refuses to touch whoever currently holds it.
 */
class PermissionsManager extends Component
{
    use BindsTenant;
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updated(string $property): void
    {
        if ($property === 'search') {
            $this->resetPage();
        }
    }

    public function updateRole(int $userId, string $role, Auditor $auditor): void
    {
        $target = User::query()->where('tenant_id', auth()->user()?->tenant_id)->findOrFail($userId);

        Gate::authorize('manageRoles', $target);

        $roleEnum = Role::tryFrom($role);
        abort_if($roleEnum?->isPlatform() === true || $roleEnum === Role::TenantOwner, 422);

        // Not a standard role: it must be one of this organization's own custom roles.
        if ($roleEnum === null) {
            abort_unless(SpatieRole::query()->where('tenant_id', auth()->user()?->tenant_id)->where('name', $role)->exists(), 422);
        }

        if ($target->id === auth()->id()) {
            Flux::toast(variant: 'danger', text: __('You cannot change your own role.'));

            return;
        }

        if ($target->hasRole(Role::TenantOwner->value)) {
            Flux::toast(variant: 'danger', text: __("The organization owner's role can only be changed by the platform admin."));

            return;
        }

        $previousRoles = $target->getRoleNames()->all();
        $target->syncRoles([$role]);

        $auditor->log('tenant.user.role_changed', $target, [
            'from' => $previousRoles,
            'to' => $role,
        ]);

        Flux::toast(variant: 'success', text: __(':name is now :role.', ['name' => $target->name, 'role' => $role]));
    }

    public function render(): View
    {
        $tenantId = auth()->user()?->tenant_id;

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->with('roles:id,name')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.settings.permissions-manager', [
            'users' => $users,
            'assignableRoles' => array_values(array_filter(Role::tenantRoles(), fn (Role $r): bool => $r !== Role::TenantOwner)),
            'customRoleNames' => SpatieRole::query()
                ->where('tenant_id', $tenantId)
                ->whereNotIn('name', array_map(fn (Role $r): string => $r->value, Role::cases()))
                ->orderBy('name')
                ->pluck('name')
                ->all(),
            'authUserId' => auth()->id(),
        ]);
    }
}
