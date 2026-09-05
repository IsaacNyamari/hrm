<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Reporting\DashboardMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardMetricsController extends Controller
{
    public function __invoke(Request $request, DashboardMetrics $metrics): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return response()->json(['data' => $metrics->for($user)]);
    }
}
