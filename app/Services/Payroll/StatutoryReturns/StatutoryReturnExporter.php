<?php

declare(strict_types=1);

namespace App\Services\Payroll\StatutoryReturns;

use App\Enums\StatutoryReturnType;
use App\Exports\ReportExport;
use App\Models\PayrollRun;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use ZipArchive;

/**
 * Renders the portal upload files. Column layouts live here in one place so a
 * portal template change is a one-method edit.
 */
class StatutoryReturnExporter
{
    private const PERSONAL_RELIEF_CENTS = 240_000;

    public function __construct(private readonly StatutoryReturnBuilder $builder) {}

    /**
     * @param  array{name: string, rows: list<array<string, mixed>>, issues: array<string, mixed>}|null  $built
     * @return array{filename: string, contents: string}
     */
    public function file(PayrollRun $run, StatutoryReturnType $type, ?array $built = null): array
    {
        $built ??= $this->builder->build($run);

        [$headings, $rows] = match ($type) {
            StatutoryReturnType::Kra => $this->kra($built['rows']),
            StatutoryReturnType::Sha => $this->sha($built['rows']),
            StatutoryReturnType::Nssf => $this->nssf($built['rows']),
        };

        return [
            'filename' => "{$type->value}-{$built['name']}.{$type->extension()}",
            'contents' => Excel::raw(new ReportExport($type->value, $headings, $rows), $type->writerType()),
        ];
    }

    /**
     * All returns in one compressed file. Returns the path of a temp zip.
     */
    public function bundle(PayrollRun $run): string
    {
        $built = $this->builder->build($run);
        $path = tempnam(sys_get_temp_dir(), 'returns');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the returns archive.');
        }

        foreach (StatutoryReturnType::cases() as $type) {
            $file = $this->file($run, $type, $built);
            $zip->addFromString($file['filename'], $file['contents']);
        }

        $zip->close();

        return $path;
    }

    /**
     * Section B of KRA's P10 return template, in the layout its "Import CSV"
     * button reads: 25 columns (A-Y) and no header row. The template recomputes
     * gross, taxable pay and PAYE itself once imported, so those columns are a
     * cross-check rather than the source of truth. Commas are dropped from names
     * because the template's own importer treats them as delimiters.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private function kra(array $rows): array
    {
        return [[], array_map(function (array $r): array {
            $preTax = $r['shif_cents'] + $r['nssf_cents'] + $r['housing_levy_cents'];

            return [
                $r['kra_pin'], str_replace(',', ' ', (string) $r['employee']), 'Resident', 'Primary Employee', 'No', '',
                $this->money($r['gross_cents']), '', '', '', 'Benefit not given', '', '', $this->money($r['gross_cents']),
                $this->money($r['shif_cents']), $this->money($r['nssf_cents']), '', '', '', $this->money($r['housing_levy_cents']),
                $this->money(max(0, $r['taxable_cents'] - $preTax)), $this->money(self::PERSONAL_RELIEF_CENTS), '',
                $this->money($r['paye_cents']), $this->money($r['paye_cents']),
            ];
        }, $rows)];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private function sha(array $rows): array
    {
        $headings = ['National ID / Passport', 'SHA / SHIF Member Number', 'Full Name', 'Payroll Number', 'Gross Pay', 'SHIF Contribution'];

        return [$headings, array_map(fn (array $r): array => [
            $r['national_id'], $r['sha_number'], $r['employee'], $r['employee_number'],
            $this->money($r['gross_cents']), $this->money($r['shif_cents']),
        ], array_values(array_filter($rows, fn (array $r): bool => $r['shif_cents'] > 0)))];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private function nssf(array $rows): array
    {
        $headings = ['Payroll Number', 'Surname', 'Other Names', 'ID Number', 'KRA PIN', 'NSSF Number', 'Gross Pay', 'Voluntary Contribution'];

        return [$headings, array_map(fn (array $r): array => [
            $r['employee_number'], $r['last_name'], $r['first_names'], $r['national_id'], $r['kra_pin'], $r['nssf_number'],
            $this->money($r['gross_cents']), $this->money(0),
        ], array_values(array_filter($rows, fn (array $r): bool => $r['nssf_cents'] > 0)))];
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
