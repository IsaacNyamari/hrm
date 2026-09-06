<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantController extends Controller
{
    /**
     * Thin wrapper: the Livewire component does the work (and re-authorizes on
     * every request). This just resolves the route-bound organization.
     */
    public function show(Request $request, Tenant $tenant): View
    {
        $user = $request->user();
        abort_unless($user !== null && $user->canOnPlatform(PlatformPermission::TenantsView), 403);

        return view('platform.tenant', ['tenant' => $tenant]);
    }
}
