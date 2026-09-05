<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Support\TenantContext;

/**
 * Ensures the current tenant is bound for every Livewire request lifecycle of
 * the component (initial render AND subsequent update requests, which post to
 * Livewire's own route outside the `tenant` middleware group). `boot()` runs on
 * every lifecycle, so this is reliable where HTTP middleware is not.
 */
trait BindsTenant
{
    public function bootBindsTenant(): void
    {
        $context = app(TenantContext::class);

        if ($context->has()) {
            return;
        }

        $tenant = auth()->user()?->tenant()->first();

        if ($tenant !== null) {
            $context->set($tenant);
        }
    }
}
