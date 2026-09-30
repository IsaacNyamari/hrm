<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Facades\Tenancy;
use App\Livewire\Concerns\BindsTenant;
use App\Models\User;
use App\Support\Auditor;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Organization-specific roles built from the permission catalogue, for jobs the
 * standard roles do not fit (for example an auditor who sees payroll but can
 * change nothing). A role can only contain permissions its creator holds, so
 * this page can never be used to gain access the user does not already have.
 */
class CustomRoles extends Component
{
    use BindsTenant;

    public ?int $editingId = null;

    public string $name = '';

    /** @var array<int, string> */
    public array $selected = [];

    public bool $showForm = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function startCreate(): void
    {
        Gate::authorize('manageRoles', auth()->user());

        $this->reset(['editingId', 'name', 'selected']);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function startEdit(int $roleId): void
    {
        Gate::authorize('manageRoles', auth()->user());

        $role = $this->customRole($roleId);
        $this->editingId = $role->getKey();
        $this->name = $role->name;
        $this->selected = $role->permissions->pluck('name')->all();
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(Auditor $auditor): void
    {
        Gate::authorize('manageRoles', auth()->user());

        $tenantId = (int) Tenancy::id();
        $reserved = array_map(fn (RoleEnum $role): string => $role->value, RoleEnum::cases());

        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:60'],
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['string', 'in:'.implode(',', Permission::values())],
        ]);

        if (in_array($this->name, $reserved, true)) {
            throw ValidationException::withMessages(['name' => __('That name is used by a standard role.')]);
        }

        $taken = Role::query()->where('tenant_id', $tenantId)->where('name', $this->name)->when($this->editingId !== null, fn ($q) => $q->whereKeyNot($this->editingId))->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => __('You already have a role with that name.')]);
        }

        $excess = array_values(array_diff($this->selected, $this->grantable()));

        if ($excess !== []) {
            throw ValidationException::withMessages(['selected' => __('You cannot grant permissions you do not hold yourself: :list', ['list' => implode(', ', $excess)])]);
        }

        if ($this->editingId === null) {
            $role = Role::query()->create(['name' => $this->name, 'guard_name' => 'web', 'tenant_id' => $tenantId]);
            $event = 'tenant.role_created';
        } else {
            $role = $this->customRole($this->editingId);
            $role->forceFill(['name' => $this->name])->save();
            $event = 'tenant.role_updated';
        }

        $before = $role->permissions->pluck('name')->sort()->values()->all();
        $role->syncPermissions($this->selected);

        $auditor->log($event, $role, ['name' => $role->name, 'permissions_before' => $before, 'permissions' => collect($this->selected)->sort()->values()->all()]);

        $this->showForm = false;
        Flux::toast(variant: 'success', text: __('Role saved.'));
    }

    public function delete(int $roleId, Auditor $auditor): void
    {
        Gate::authorize('manageRoles', auth()->user());

        $role = $this->customRole($roleId);

        if ($role->users()->exists()) {
            Flux::toast(variant: 'danger', text: __('Move everyone off this role before deleting it.'));

            return;
        }

        $auditor->log('tenant.role_deleted', $role, ['name' => $role->name]);
        $role->delete();
        Flux::toast(variant: 'success', text: __('Role deleted.'));
    }

    public function render(): View
    {
        $roles = Role::query()
            ->where('tenant_id', Tenancy::id())
            ->whereNotIn('name', array_map(fn (RoleEnum $role): string => $role->value, RoleEnum::cases()))
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return view('livewire.settings.custom-roles', [
            'roles' => $roles,
            'grantable' => $this->grantable(),
            'canManage' => Gate::allows('manageRoles', auth()->user()),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function grantable(): array
    {
        $user = auth()->user();

        return array_values(array_filter(Permission::values(), fn (string $permission): bool => $user !== null && $user->can($permission)));
    }

    private function customRole(int $roleId): Role
    {
        $role = Role::query()->where('tenant_id', Tenancy::id())->findOrFail($roleId);
        abort_if(in_array($role->name, array_map(fn (RoleEnum $r): string => $r->value, RoleEnum::cases()), true), 403);

        return $role;
    }
}
