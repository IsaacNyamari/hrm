<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Today's attendance for the bound organisation: who is in, late, on break or
 * gone home, and every record newest-first. Shared by the web "Live today"
 * screen and the mobile "Team today" tab so both count the same way.
 */
final readonly class TodaySnapshot
{
    /**
     * @param  array{present: int, late: int, on_break: int, clocked_out: int, not_in: int, headcount: int}  $stats
     * @param  Collection<int, Attendance>  $records
     */
    public function __construct(
        public array $stats,
        public Collection $records,
    ) {}

    public static function build(): self
    {
        $records = Attendance::query()
            ->whereDate('work_date', CarbonImmutable::today()->toDateString())
            ->with(['employee:id,first_name,last_name,employee_number'])
            ->orderByDesc('updated_at')
            ->get();

        $headcount = Employee::query()->active()->count();
        $in = $records->whereNotNull('clock_in_at');

        return new self([
            'present' => $in->count(),
            'late' => $in->where('status', AttendanceStatus::Late)->count(),
            'on_break' => $in->filter(fn (Attendance $a) => $a->isOnBreak())->count(),
            'clocked_out' => $in->whereNotNull('clock_out_at')->count(),
            'not_in' => max(0, $headcount - $in->count()),
            'headcount' => $headcount,
        ], $records);
    }
}
