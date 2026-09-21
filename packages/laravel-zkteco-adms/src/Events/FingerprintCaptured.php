<?php

declare(strict_types=1);

namespace Athwari\LaravelZktecoAdms\Events;

use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Models\ZktecoUser;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A fingerprint was enrolled (or re-enrolled) directly at a device and the
 * device reported the resulting template back via its OPERLOG push. The
 * template is already in that device's native algorithm format, so it can be
 * queued straight back out to the user's other devices via
 * CommandManager::sendFingerprintCommand() without any conversion.
 */
class FingerprintCaptured
{
    use Dispatchable;

    public function __construct(
        public ZktecoUser $user,
        public ZktecoDevice $device,
        public int $fingerIndex,
        public string $template,
    ) {}
}
