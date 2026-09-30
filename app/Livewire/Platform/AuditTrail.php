<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Enums\PlatformPermission;
use App\Livewire\Concerns\BindsTenant;
use App\Livewire\Platform\Concerns\WithPlatformGuard;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Everything platform operators did (suspensions, plan edits, deployments,
 * impersonation) across every organization, newest first.
 */
class AuditTrail extends Component
{
    use BindsTenant;
    use WithPagination;
    use WithPlatformGuard;

    public string $event = '';

    public string $actor = '';

    public function mount(): void
    {
        $this->authorizePlatform(PlatformPermission::AuditView);
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorizePlatform(PlatformPermission::AuditView);

        $logs = AuditLog::query()
            ->where(fn ($q) => $q->where('event', 'like', 'platform.%')->orWhere('via_impersonation', true))
            ->when($this->event !== '', fn ($q) => $q->where('event', 'like', $this->event.'%'))
            ->when($this->actor !== '', fn ($q) => $q->where('actor_label', 'like', '%'.$this->actor.'%'))
            ->latest('id')
            ->paginate(30);

        return view('livewire.platform.audit-trail', ['logs' => $logs]);
    }
}
