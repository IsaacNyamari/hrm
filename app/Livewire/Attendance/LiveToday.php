<?php

declare(strict_types=1);

namespace App\Livewire\Attendance;

use App\Livewire\Concerns\BindsTenant;
use App\Models\Attendance;
use App\Support\Attendance\TodaySnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Today's attendance at a glance for HR: who is in, late, on break or gone
 * home, and the latest punches. Updates live as people clock in and out.
 */
class LiveToday extends Component
{
    use BindsTenant;

    public ?string $lastEvent = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Attendance::class);
    }

    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $tenantId = auth()->user()?->tenant_id;

        return $tenantId === null ? [] : [
            "echo-private:tenant.{$tenantId}.attendance,.attendance.logged" => 'attendanceLogged',
        ];
    }

    /**
     * @param  array{message?: string}  $payload
     */
    public function attendanceLogged(array $payload = []): void
    {
        $this->lastEvent = (string) ($payload['message'] ?? '');
    }

    public function render(): View
    {
        $snapshot = TodaySnapshot::build();

        return view('livewire.attendance.live-today', [
            'stats' => $snapshot->stats,
            'latest' => $snapshot->records->take(8),
        ]);
    }
}
