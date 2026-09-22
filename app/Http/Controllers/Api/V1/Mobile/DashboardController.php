<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Exceptions\AttendanceException;
use App\Http\Controllers\Controller;
use App\Services\SelfService\SelfServiceDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "My HR" for the token owner — the same SelfServiceDashboard payload the web
 * `me/dashboard` endpoint returns, so every figure matches the browser.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, SelfServiceDashboard $dashboard): JsonResponse
    {
        $employee = $request->user()?->employee;

        if ($employee === null) {
            throw AttendanceException::noEmployeeRecord();
        }

        return response()->json(['data' => $dashboard->for($employee)]);
    }
}
