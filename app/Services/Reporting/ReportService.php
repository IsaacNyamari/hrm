<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\User;
use App\Reports\Report;
use App\Reports\ReportRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportService
{
    public function __construct(private readonly ReportRegistry $registry) {}

    public function report(string $key, User $user): Report
    {
        $report = $this->registry->find($key);

        if ($report === null) {
            throw new NotFoundHttpException("Unknown report [{$key}].");
        }

        if (! $this->registry->canRun($user, $report)) {
            throw new AuthorizationException('You do not have access to this report.');
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{key: string, name: string, category: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>, summary: array<string, string|int|float>, filters: array<int, array{key: string, label: string, type: string, required?: bool}>, filters_applied: array<string, mixed>, generated_at: string}
     */
    public function run(string $key, array $filters, User $user): array
    {
        $report = $this->report($key, $user);
        $clean = $this->sanitise($report, $filters);

        $rows = [];
        foreach ($report->rows($clean) as $row) {
            $rows[] = array_values($row);
        }

        return [
            'key' => $report->key(),
            'name' => $report->name(),
            'category' => $report->category(),
            'columns' => $report->columns(),
            'rows' => $rows,
            'summary' => $report->summary($clean),
            'filters' => $report->filters(),
            'filters_applied' => $clean,
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * Keep only declared filter keys with non-empty scalar values.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function sanitise(Report $report, array $filters): array
    {
        $allowed = array_map(static fn (array $f): string => $f['key'], $report->filters());
        $clean = [];

        foreach ($allowed as $key) {
            $value = $filters[$key] ?? null;

            if (is_scalar($value) && (string) $value !== '') {
                $clean[$key] = (string) $value;
            }
        }

        return $clean;
    }
}
