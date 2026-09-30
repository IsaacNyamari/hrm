<?php

declare(strict_types=1);

namespace App\Enums;

enum WebhookEvent: string
{
    case EmployeeCreated = 'employee.created';
    case EmployeeUpdated = 'employee.updated';
    case LeaveApproved = 'leave.approved';
    case LeaveRejected = 'leave.rejected';
    case ExpenseApproved = 'expense.approved';
    case ExpenseRejected = 'expense.rejected';
    case EmployeeTerminated = 'employee.terminated';
    case PayrollApproved = 'payroll.approved';
    case PayrollPaid = 'payroll.paid';
    case ApplicationReceived = 'application.received';
    case OfferAccepted = 'offer.accepted';
    case Ping = 'webhook.ping';

    public function label(): string
    {
        return ucwords(str_replace(['.', '_'], ' ', $this->value));
    }

    /**
     * Events a tenant may subscribe an endpoint to (excludes internal ones).
     *
     * @return array<int, string>
     */
    public static function subscribable(): array
    {
        return array_values(array_map(
            static fn (self $e): string => $e->value,
            array_filter(self::cases(), static fn (self $e): bool => $e !== self::Ping),
        ));
    }
}
