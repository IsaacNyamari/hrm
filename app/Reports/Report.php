<?php

declare(strict_types=1);

namespace App\Reports;

use App\Support\TenantContext;

/**
 * A tenant-scoped report. Implementations only ever read data through the
 * bound {@see TenantContext}, so a report can never emit another
 * tenant's rows. The registry gates each report on `permissions()` plus the
 * global `reports.view`.
 */
interface Report
{
    public function key(): string;

    public function name(): string;

    public function category(): string;

    /**
     * Tenant permission strings the caller must hold (in addition to
     * `reports.view`) to run this report.
     *
     * @return array<int, string>
     */
    public function permissions(): array;

    /**
     * Filter fields the UI should render.
     *
     * @return array<int, array{key: string, label: string, type: string, required?: bool}>
     */
    public function filters(): array;

    /**
     * @return array<int, string>
     */
    public function columns(): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return iterable<int, array<int, string|int|float|null>>
     */
    public function rows(array $filters): iterable;

    /**
     * Headline figures for the report (rendered above the table).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int|float>
     */
    public function summary(array $filters): array;
}
