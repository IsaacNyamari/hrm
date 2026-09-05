<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Enums\AttendanceSource;
use Carbon\CarbonImmutable;

/**
 * A source-agnostic attendance punch. Web self-service, mobile, a biometric
 * device webhook and admin entry all normalise to this before the
 * AttendanceService applies it.
 */
final readonly class PunchData
{
    public function __construct(
        public AttendanceSource $source,
        public ?CarbonImmutable $at = null,
        public ?string $ip = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $location = null,
        public ?int $recordedBy = null,
    ) {}

    public function time(): CarbonImmutable
    {
        return $this->at ?? CarbonImmutable::now();
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
