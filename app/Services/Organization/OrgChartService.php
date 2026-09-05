<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Builds organisational trees in memory from a single query each — no N+1.
 * Every query is tenant-scoped by the bound context.
 */
class OrgChartService
{
    /**
     * Reporting lines: employees nested under their manager. Roots are employees
     * with no manager (or a manager outside the active set).
     *
     * @return array<int, array<string, mixed>>
     */
    public function reportingTree(): array
    {
        $employees = Employee::query()
            ->active()
            ->with('department:id,name')
            ->get(['id', 'uuid', 'first_name', 'middle_name', 'last_name', 'job_title', 'manager_id', 'department_id']);

        $byManager = $employees->groupBy('manager_id');
        $knownIds = $employees->pluck('id')->flip();

        $build = function (Employee $employee) use (&$build, $byManager): array {
            /** @var Collection<int, Employee> $reports */
            $reports = $byManager->get($employee->id, new Collection);

            return [
                'uuid' => $employee->uuid,
                'name' => $employee->fullName(),
                'job_title' => $employee->job_title,
                'department' => $employee->department?->name,
                'reports' => $reports->map($build)->values()->all(),
            ];
        };

        return $employees
            ->filter(fn (Employee $e): bool => $e->manager_id === null || ! $knownIds->has($e->manager_id))
            ->map($build)
            ->values()
            ->all();
    }

    /**
     * Department hierarchy nested by parent_id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function departmentTree(): array
    {
        $departments = Department::query()
            ->with('head:id,first_name,middle_name,last_name')
            ->get(['id', 'uuid', 'name', 'code', 'parent_id', 'head_employee_id']);

        $byParent = $departments->groupBy('parent_id');
        $knownIds = $departments->pluck('id')->flip();

        $build = function (Department $department) use (&$build, $byParent): array {
            /** @var Collection<int, Department> $children */
            $children = $byParent->get($department->id, new Collection);

            return [
                'uuid' => $department->uuid,
                'name' => $department->name,
                'code' => $department->code,
                'head' => $department->head?->fullName(),
                'children' => $children->map($build)->values()->all(),
            ];
        };

        return $departments
            ->filter(fn (Department $d): bool => $d->parent_id === null || ! $knownIds->has($d->parent_id))
            ->map($build)
            ->values()
            ->all();
    }
}
