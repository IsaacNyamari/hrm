<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PlatformMetrics $metrics): View
    {
        abort_unless($request->user()?->canOnPlatform(PlatformPermission::DashboardView), 403);

        return view('platform.dashboard', $metrics->snapshot());
    }
}
