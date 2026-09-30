<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Employee;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * Renders an HR letter template by substituting `{{ placeholders }}` from a
 * whitelisted variable map. Pure string replacement — no Blade, no `eval`, no
 * PHP evaluation, so a template body is safe user-editable content.
 */
class TemplateRenderer
{
    /**
     * @param  array<string, string|int|float|null>  $extras  extra `{{ key }}` values supplied at generation time
     * @return array{body: string, subject: string, context: array<string, string>, missing: array<int, string>}
     */
    public function render(string $subject, string $body, Employee $employee, Tenant $tenant, array $extras = []): array
    {
        $map = $this->variables($employee, $tenant) + $this->normaliseExtras($extras);

        $missing = [];
        $renderedSubject = $this->substitute($subject, $map, $missing);
        $renderedBody = $this->substitute($body, $map, $missing);

        return [
            'subject' => $renderedSubject,
            'body' => $renderedBody,
            'context' => $map,
            'missing' => array_values(array_unique($missing)),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function variables(Employee $employee, Tenant $tenant): array
    {
        $employee->loadMissing(['department:id,name', 'designation:id,name', 'branch:id,name']);
        $now = CarbonImmutable::now();

        $salary = $employee->salary_amount_cents !== null
            ? ($employee->salary_currency ?? $tenant->currency).' '.number_format($employee->salary_amount_cents / 100, 2)
            : '';

        return [
            'employee.name' => $employee->fullName(),
            'employee.first_name' => (string) $employee->first_name,
            'employee.last_name' => (string) $employee->last_name,
            'employee.number' => $employee->employee_number,
            'employee.email' => (string) $employee->email,
            'employee.phone' => (string) $employee->phone,
            'employee.position' => (string) ($employee->job_title ?? $employee->designation?->name),
            'employee.job_title' => (string) $employee->job_title,
            'employee.department' => (string) $employee->department?->name,
            'employee.designation' => (string) $employee->designation?->name,
            'employee.branch' => (string) $employee->branch?->name,
            'employee.hire_date' => $employee->hire_date?->toFormattedDateString() ?? '',
            'employee.exit_date' => $employee->exit_date?->toFormattedDateString() ?? '',
            'employee.salary' => $salary,
            'company.name' => $tenant->name,
            'company.legal_name' => (string) ($tenant->legal_name ?? $tenant->name),
            'company.tax_number' => (string) $tenant->tax_number,
            'company.email' => (string) $tenant->email,
            'company.phone' => (string) $tenant->phone,
            'company.address' => trim(implode(', ', array_filter([
                $tenant->address_line1, $tenant->city, $tenant->country,
            ]))),
            'date' => $now->toFormattedDateString(),
            'today' => $now->toDateString(),
            'year' => (string) $now->year,
        ];
    }

    /**
     * @param  array<string, string>  $map
     * @param  list<string>  $missing
     */
    private function substitute(string $text, array $map, array &$missing): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            static function (array $m) use ($map, &$missing): string {
                $key = $m[1];

                if (array_key_exists($key, $map)) {
                    return $map[$key];
                }

                $missing[] = $key;

                return '';
            },
            $text,
        );
    }

    /**
     * @param  array<string, string|int|float|null>  $extras
     * @return array<string, string>
     */
    private function normaliseExtras(array $extras): array
    {
        $out = [];

        foreach ($extras as $key => $value) {
            if (preg_match('/^[a-zA-Z0-9_.]+$/', (string) $key) === 1) {
                $out[$key] = (string) ($value ?? '');
            }
        }

        return $out;
    }
}
