<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Exceptions\AttendanceException;
use App\Http\Controllers\Controller;
use App\Services\SelfService\SelfServiceDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SelfServiceDashboard $dashboard): JsonResponse|View
    {
        $employee = $request->user()?->employee;

        if ($employee === null) {
            if (! $request->wantsJson()) {
                return view('portal.dashboard');
            }

            throw AttendanceException::noEmployeeRecord();
        }

        if (! $request->wantsJson()) {
            return view('portal.dashboard');
        }

        return response()->json(['data' => $dashboard->for($employee)]);
    }
}
