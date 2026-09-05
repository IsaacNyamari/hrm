<?php

namespace Database\Factories;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->unique()->dateTimeBetween('-90 days', 'yesterday');
        $in = (clone $date)->setTime(8, fake()->numberBetween(0, 20));
        $out = (clone $date)->setTime(17, fake()->numberBetween(0, 40));
        $worked = (int) (($out->getTimestamp() - $in->getTimestamp()) / 60) - 60;

        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'work_date' => $date->format('Y-m-d'),
            'clock_in_at' => $in,
            'clock_out_at' => $out,
            'break_minutes' => 60,
            'worked_minutes' => $worked,
            'overtime_minutes' => max(0, $worked - 480),
            'status' => AttendanceStatus::Present,
            'source' => AttendanceSource::Web,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    public function forEmployee(Employee $employee): static
    {
        // Distinct work_date per row for this employee (unique tenant+employee+date).
        return $this->state(fn (): array => [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->getKey(),
        ])->state(new Sequence(
            fn (Sequence $sequence): array => ['work_date' => now()->subDays($sequence->index + 1)->toDateString()],
        ));
    }
}
