<?php

declare(strict_types=1);

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads a spreadsheet into heading-keyed rows for validation + preview before
 * anything is written. No models are created here.
 */
class EmployeeSheetImport implements ToArray, WithHeadingRow
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function array(array $rows): void
    {
        $this->rows = $rows;
    }
}
