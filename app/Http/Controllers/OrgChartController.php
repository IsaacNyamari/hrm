<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\Organization\OrgChartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrgChartController extends Controller
{
    public function __invoke(Request $request, OrgChartService $chart): JsonResponse|View
    {
        $this->authorize('viewAny', Employee::class);

        if (! $request->wantsJson()) {
            return view('organization.chart');
        }

        return response()->json(['data' => $chart->reportingTree()]);
    }
}
