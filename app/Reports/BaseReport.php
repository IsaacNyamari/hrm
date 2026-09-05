<?php

declare(strict_types=1);

namespace App\Reports;

use Carbon\CarbonImmutable;

abstract class BaseReport implements Report
{
    public function category(): string
    {
        return 'General';
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return [];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required?: bool}>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int|float>
     */
    public function summary(array $filters): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function date(array $filters, string $key, ?CarbonImmutable $default = null): ?CarbonImmutable
    {
        $value = $filters[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return $default;
        }

        return CarbonImmutable::parse($value);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function int(array $filters, string $key): ?int
    {
        $value = $filters[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function str(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
