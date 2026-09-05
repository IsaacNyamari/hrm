<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $employees = Employee::query()
            ->with(['department:id,uuid,name', 'branch:id,uuid,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->orderBy('employee_number')
            ->paginate(min($request->integer('per_page', 25), 100));

        return EmployeeResource::collection($employees);
    }

    public function show(string $employee): EmployeeResource
    {
        $record = Employee::query()
            ->with(['department:id,uuid,name', 'branch:id,uuid,name', 'manager:id,uuid'])
            ->where('uuid', $employee)
            ->firstOrFail();

        return new EmployeeResource($record);
    }
}
