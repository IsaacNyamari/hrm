<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;

/**
 * Maps any status value (leave, attendance, payroll, recruitment, billing...)
 * to a Flux badge colour, so positive outcomes read green, in-flight work
 * amber, and failures red across the whole app.
 */
class StatusTone
{
    private const POSITIVE = [
        'active', 'approved', 'paid', 'present', 'completed', 'hired', 'accepted', 'succeeded',
        'reimbursed', 'settled', 'acknowledged', 'resolved', 'filled', 'available', 'remote_work',
    ];

    private const PENDING = [
        'pending', 'submitted', 'in_progress', 'in_review', 'processed', 'trialing', 'probation',
        'past_due', 'late', 'early_departure', 'half_day', 'on_hold', 'assigned', 'sent', 'in_repair',
    ];

    private const NEGATIVE = [
        'rejected', 'cancelled', 'failed', 'expired', 'absent', 'terminated', 'suspended', 'declined',
        'rescinded', 'lost', 'uncollectible', 'void', 'withdrawn',
    ];

    private const INFORMATIONAL = ['open', 'on_leave', 'holiday', 'scheduled'];

    public static function for(BackedEnum|string|null $status): string
    {
        $value = $status instanceof BackedEnum ? (string) $status->value : (string) $status;

        return match (true) {
            in_array($value, self::POSITIVE, true) => 'green',
            in_array($value, self::PENDING, true) => 'amber',
            in_array($value, self::NEGATIVE, true) => 'red',
            in_array($value, self::INFORMATIONAL, true) => 'blue',
            default => 'zinc',
        };
    }
}
