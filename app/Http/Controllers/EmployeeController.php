<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\JsonResponse;

/**
 * Phase 1 placeholder — proves tenant isolation end to end over HTTP. Replaced
 * by Livewire screens in Phase 4.
 */
class EmployeeController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        return response()->json([
            'data' => Employee::query()
                ->orderBy('employee_number')
                ->get(['uuid', 'employee_number', 'first_name', 'last_name', 'email', 'status']),
        ]);
    }

    public function show(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return response()->json([
            'data' => $employee->only([
                'uuid', 'employee_number', 'first_name', 'middle_name',
                'last_name', 'email', 'phone', 'job_title', 'status',
            ]),
        ]);
    }
}
