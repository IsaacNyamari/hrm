<?php

declare(strict_types=1);

namespace App\Enums;

use Maatwebsite\Excel\Excel;

/**
 * The portal upload files generated from a payroll run. The government portals
 * expose no employer write API, so each return is a file in the format the
 * portal accepts, to be uploaded by the employer.
 */
enum StatutoryReturnType: string
{
    case Kra = 'kra';
    case Sha = 'sha';
    case Nssf = 'nssf';

    public function label(): string
    {
        return match ($this) {
            self::Kra => 'KRA iTax (PAYE + Housing Levy)',
            self::Sha => 'SHA employer portal (SHIF)',
            self::Nssf => 'NSSF employer portal',
        };
    }

    public function extension(): string
    {
        return $this === self::Sha ? 'xlsx' : 'csv';
    }

    public function writerType(): string
    {
        return $this === self::Sha ? Excel::XLSX : Excel::CSV;
    }
}
