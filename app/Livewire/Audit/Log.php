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

    public string $actor = '';

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('audit.view'), 403);
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $logs = AuditLog::query()
            ->where('tenant_id', Tenancy::id())
            ->when($this->event !== '', fn ($q) => $q->where('event', 'like', $this->event.'%'))
            ->when($this->actor !== '', fn ($q) => $q->where('actor_label', 'like', '%'.$this->actor.'%'))
            ->when($this->from !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->latest('id')
            ->paginate(30);

        return view('livewire.audit.log', [
            'logs' => $logs,
            'exportUrl' => route('audit-logs.export', array_filter(['event' => $this->event, 'actor' => $this->actor, 'from' => $this->from, 'to' => $this->to])),
            'canExport' => (bool) auth()->user()?->can('reports.export'),
        ]);
    }
}
