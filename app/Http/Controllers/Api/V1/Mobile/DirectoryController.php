<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\DirectoryEmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * A searchable staff directory (name, role, department, work contacts) for
 * users who may view employees — the same `employees.view` gate as the web.
 */
class DirectoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Employee::class);

        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? null;

        $employees = Employee::query()
            ->active()
            ->when($search !== null && trim($search) !== '', fn ($q) => $q->search(trim((string) $search)))
            ->with(['department:id,name', 'branch:id,name'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(min($request->integer('per_page', 30), 100));

        return DirectoryEmployeeResource::collection($employees);
    }

    public function show(string $employee): DirectoryEmployeeResource
    {
        $record = Employee::query()
            ->where('uuid', $employee)
            ->with(['department:id,name', 'branch:id,name', 'manager:id,first_name,middle_name,last_name'])
            ->firstOrFail();

        $this->authorize('view', $record);

        return new DirectoryEmployeeResource($record);
    }
}
