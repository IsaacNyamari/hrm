<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentCategory: string
{
    case NationalId = 'national_id';
    case Contract = 'contract';
    case Certificate = 'certificate';
    case Cv = 'cv';
    case Payslip = 'payslip';
    case Appraisal = 'appraisal';
    case Warning = 'warning';
    case Disciplinary = 'disciplinary';
    case Medical = 'medical';
    case Termination = 'termination';
    case Other = 'other';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /**
     * Categories an employee may upload for themselves.
     *
     * @return array<int, self>
     */
    public static function employeeUploadable(): array
    {
        return [self::NationalId, self::Certificate, self::Cv, self::Medical, self::Other];
    }
}
