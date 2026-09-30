<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * Joiners and leavers in a date range (default: the current year).
 */
class HeadcountMovementReport extends BaseReport
{
    public function key(): string
    {
        return 'headcount-movement';
    }

    public function name(): string
    {
        return 'Headcount movement (joiners and leavers)';
    }

    public function category(): string
    {
        return 'People';
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required?: bool}>
     */
    public function filters(): array
    {
        return [
            ['key' => 'from', 'label' => 'From', 'type' => 'date'],
            ['key' => 'to', 'label' => 'To', 'type' => 'date'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function columns(): array
    {
        return ['Movement', 'Employee', 'Number', 'Department', 'Date', 'Status'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return iterable<int, array<int, string|int|float|null>>
     */
    public function rows(array $filters): iterable
    {
        [$from, $to] = $this->range($filters);

        $joiners = Employee::query()->with('department:id,name')->whereBetween('hire_date', [$from->toDateString(), $to->toDateString()])->get()
            ->map(fn (Employee $e): array => ['Joiner', $e->fullName(), $e->employee_number, (string) ($e->department?->name ?? '—'), $e->hire_date?->toDateString(), $e->status->value]);

        $leavers = Employee::query()->with('department:id,name')->whereBetween('exit_date', [$from->toDateString(), $to->toDateString()])->get()
            ->map(fn (Employee $e): array => ['Leaver', $e->fullName(), $e->employee_number, (string) ($e->department?->name ?? '—'), $e->exit_date?->toDateString(), $e->status->value]);

        return $joiners->concat($leavers)->sortBy(fn (array $row): string => (string) $row[4])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int|float>
     */
    public function summary(array $filters): array
    {
        [$from, $to] = $this->range($filters);

        $joiners = Employee::query()->whereBetween('hire_date', [$from->toDateString(), $to->toDateString()])->count();
        $leavers = Employee::query()->whereBetween('exit_date', [$from->toDateString(), $to->toDateString()])->count();

        return ['Joiners' => $joiners, 'Leavers' => $leavers, 'Net movement' => $joiners - $leavers];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(array $filters): array
    {
        return [
            $this->date($filters, 'from', CarbonImmutable::now()->startOfYear()),
            $this->date($filters, 'to', CarbonImmutable::now()->endOfYear()),
        ];
    }
}
