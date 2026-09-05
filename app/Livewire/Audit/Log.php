<?php

declare(strict_types=1);

namespace App\Livewire\Audit;

use App\Facades\Tenancy;
use App\Livewire\Concerns\BindsTenant;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Log extends Component
{
    use BindsTenant;
    use WithPagination;

    public string $event = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('audit.view'), 403);
    }

    public function updatedEvent(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $logs = AuditLog::query()
            ->where('tenant_id', Tenancy::id())
            ->when($this->event !== '', fn ($q) => $q->where('event', 'like', $this->event.'%'))
            ->latest('id')
            ->paginate(30);

        return view('livewire.audit.log', ['logs' => $logs]);
    }
}
