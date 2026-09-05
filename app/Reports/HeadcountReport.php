<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Employee;

class HeadcountReport extends BaseReport
{
    public function key(): string
    {
        return 'headcount';
    }

    public function name(): string
    {
        return 'Headcount by department';
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
            ['key' => 'status', 'label' => 'Employment status', 'type' => 'text'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function columns(): array
    {
        return ['Department', 'Branch', 'Status', 'Headcount'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return iterable<int, array<int, string|int|float|null>>
     */
    public function rows(array $filters): iterable
    {
        $status = $this->str($filters, 'status');

        return Employee::query()
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->with(['department:id,name', 'branch:id,name'])
            ->get(['id', 'tenant_id', 'department_id', 'branch_id', 'status'])
            ->groupBy(fn (Employee $e): string => implode('|', [
                (string) (data_get($e, 'department.name') ?? '—'),
                (string) (data_get($e, 'branch.name') ?? '—'),
                $e->status->value,
            ]))
            ->map(function ($group, string $key): array {
                [$dept, $branch, $status] = explode('|', $key);

                return [$dept, $branch, $status, $group->count()];
            })
            ->sortBy(fn (array $row): string => $row[0])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int|float>
     */
    public function summary(array $filters): array
    {
        $status = $this->str($filters, 'status');

        return [
            'Total employees' => Employee::query()
                ->when($status !== null, fn ($q) => $q->where('status', $status))
                ->count(),
            'Active' => Employee::query()->active()->count(),
        ];
    }
}
