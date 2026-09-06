<?php

declare(strict_types=1);

namespace App\Livewire\Platform\Concerns;

use App\Enums\PlatformPermission;

/**
 * Authorization for platform (SaaS operator) Livewire components. Livewire's
 * update requests post to Livewire's own route, which is NOT behind the
 * `platform` HTTP middleware — so the gate has to run on every component
 * lifecycle. `boot()` does exactly that; each mutating action additionally
 * re-checks its specific permission.
 */
trait WithPlatformGuard
{
    public function bootWithPlatformGuard(): void
    {
        $user = auth()->user();

        abort_unless($user !== null && $user->isPlatformAdmin() && $user->hasPlatformRole(), 403);
    }

    protected function authorizePlatform(PlatformPermission $permission): void
    {
        $user = auth()->user();

        abort_unless($user !== null && $user->canOnPlatform($permission), 403);
    }
}
