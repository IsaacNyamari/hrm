<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationCategory: string
{
    case Leave = 'leave';
    case Payroll = 'payroll';
    case Expenses = 'expenses';
    case Performance = 'performance';
    case Recruitment = 'recruitment';
    case Documents = 'documents';
    case Announcements = 'announcements';
    case HrRequests = 'hr_requests';
    case Billing = 'billing';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::HrRequests => 'HR requests',
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }

    /**
     * Categories a user may silence e-mail for. In-app (database) delivery is
     * always kept.
     *
     * @return array<int, self>
     */
    public static function userConfigurable(): array
    {
        return [
            self::Leave, self::Payroll, self::Expenses, self::Performance,
            self::Recruitment, self::Documents, self::Announcements, self::HrRequests,
        ];
    }
}
