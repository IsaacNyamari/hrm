<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isPlatformAdmin() === true) {
            return redirect()->route('platform.dashboard');
        }

        if ($request->user()?->isSelfServiceOnly() === true) {
            return redirect()->route('portal');
        }

        return view('dashboard');
    }
}
