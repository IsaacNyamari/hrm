<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Models\Department;
use App\Models\Employee;
use App\Support\TenantCache;
use Illuminate\Support\Collection;

/**
 * Builds organisational trees in memory from a single query each — no N+1.
 * Every query is tenant-scoped by the bound context, and the assembled tree is
 * cached per tenant for a short window (structural changes surface within it).
 */
class OrgChartService
{
    private const CACHE_TTL = 120;

    /**
     * Reporting lines: employees nested under their manager. Roots are employees
     * with no manager (or a manager outside the active set).
     *
     * @return array<int, array<string, mixed>>
     */
    public function reportingTree(): array
    {
        return TenantCache::remember('org.reporting-tree.v2', self::CACHE_TTL, fn (): array => $this->buildReportingTree());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildReportingTree(): array
    {
        $employees = Employee::query()
            ->active()
            ->with('department:id,name')
            ->get(['id', 'uuid', 'first_name', 'middle_name', 'last_name', 'job_title', 'manager_id', 'department_id']);

        $byManager = $employees->groupBy('manager_id');
        $knownIds = $employees->pluck('id')->flip();

        $build = function (Employee $employee) use (&$build, $byManager): array {
            /** @var Collection<int, Employee> $reports */
            $reports = $byManager->get($employee->id, new Collection)->map($build)->values()->all();

            return [
                'uuid' => $employee->uuid,
                'name' => $employee->fullName(),
                'initials' => $this->initials($employee->first_name.' '.$employee->last_name),
                'job_title' => $employee->job_title,
                'department' => $employee->department?->name,
                'reports' => $reports,
                'team_size' => array_sum(array_map(fn (array $r): int => 1 + $r['team_size'], $reports)),
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
        return TenantCache::remember('org.department-tree.v2', self::CACHE_TTL, fn (): array => $this->buildDepartmentTree());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildDepartmentTree(): array
    {
        $departments = Department::query()
            ->with('head:id,first_name,middle_name,last_name')
            ->get(['id', 'uuid', 'name', 'code', 'parent_id', 'head_employee_id']);

        $byParent = $departments->groupBy('parent_id');
        $knownIds = $departments->pluck('id')->flip();
        $headcounts = Employee::query()->active()->whereNotNull('department_id')
            ->selectRaw('department_id, count(*) as aggregate')->groupBy('department_id')
            ->pluck('aggregate', 'department_id');

        $build = function (Department $department) use (&$build, $byParent, $headcounts): array {
            /** @var Collection<int, Department> $children */
            $children = $byParent->get($department->id, new Collection)->map($build)->values()->all();
            $headcount = (int) ($headcounts[$department->id] ?? 0);

            return [
                'uuid' => $department->uuid,
                'name' => $department->name,
                'initials' => $this->initials($department->name),
                'code' => $department->code,
                'head' => $department->head?->fullName(),
                'headcount' => $headcount,
                'children' => $children,
                'team_size' => $headcount + array_sum(array_map(fn (array $c): int => $c['team_size'], $children)),
            ];
        };

        return $departments
            ->filter(fn (Department $d): bool => $d->parent_id === null || ! $knownIds->has($d->parent_id))
            ->map($build)
            ->values()
            ->all();
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');
    }
}
