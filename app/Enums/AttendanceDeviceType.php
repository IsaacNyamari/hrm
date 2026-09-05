<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Physical/logical sources of attendance punches. The application never talks to
 * hardware directly — a device pushes punches to the ingestion webhook, which
 * normalises them regardless of type.
 */
enum AttendanceDeviceType: string
{
    case Web = 'web';
    case MobileApp = 'mobile_app';
    case Fingerprint = 'fingerprint';
    case FacialRecognition = 'facial_recognition';
    case Rfid = 'rfid';
    case Api = 'api';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
