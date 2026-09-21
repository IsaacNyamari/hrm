<?php

declare(strict_types=1);

namespace App\Livewire\Attendance;

use App\Enums\AttendanceStatus;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
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
        $today = CarbonImmutable::today()->toDateString();

        $records = Attendance::query()
            ->whereDate('work_date', $today)
            ->with(['employee:id,first_name,last_name,employee_number'])
            ->orderByDesc('updated_at')
            ->get();

        $headcount = Employee::query()->active()->count();
        $in = $records->whereNotNull('clock_in_at');

        return view('livewire.attendance.live-today', [
            'stats' => [
                'present' => $in->count(),
                'late' => $in->where('status', AttendanceStatus::Late)->count(),
                'on_break' => $in->filter(fn (Attendance $a) => $a->isOnBreak())->count(),
                'clocked_out' => $in->whereNotNull('clock_out_at')->count(),
                'not_in' => max(0, $headcount - $in->count()),
            ],
            'latest' => $records->take(8),
        ]);
    }
}
