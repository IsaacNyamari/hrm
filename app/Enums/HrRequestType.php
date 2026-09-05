<?php

declare(strict_types=1);

namespace App\Enums;

enum HrRequestType: string
{
    case SalaryCertificate = 'salary_certificate';
    case EmploymentLetter = 'employment_letter';
    case LeaveIssue = 'leave_issue';
    case PayrollIssue = 'payroll_issue';
    case ProfileUpdate = 'profile_update';
    case DocumentRequest = 'document_request';
    case Other = 'other';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
