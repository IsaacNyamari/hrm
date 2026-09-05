<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Jobs\ProcessEmployeeImport;
use App\Models\Employee;
use App\Models\EmployeeImport;
use App\Services\Employees\EmployeeImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeImportController extends Controller
{
    /**
     * Upload a spreadsheet and get a validation report. Nothing is written yet;
     * the file is kept for a follow-up confirm call.
     */
    public function preview(Request $request, EmployeeImporter $importer): JsonResponse
    {
        $this->authorize('create', Employee::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        $tenant = Tenancy::getOrFail();
        $path = $request->file('file')->store("imports/{$tenant->uuid}", 'local');

        $import = EmployeeImport::query()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $request->user()?->getKey(),
            'original_filename' => $request->file('file')->getClientOriginalName(),
            'storage_path' => $path,
            'status' => EmployeeImport::STATUS_PENDING,
        ]);

        return response()->json([
            'data' => ['import' => $import->uuid] + $importer->preview($import),
        ]);
    }

    /**
     * Queue the actual import for a previously previewed file.
     */
    public function confirm(EmployeeImport $import): JsonResponse
    {
        $this->authorize('create', Employee::class);

        abort_unless($import->status === EmployeeImport::STATUS_PENDING, 409, 'This import has already been processed.');

        ProcessEmployeeImport::dispatch($import->getKey());

        return response()->json(['data' => ['import' => $import->uuid, 'status' => 'queued']], 202);
    }

    public function show(EmployeeImport $import): JsonResponse
    {
        $this->authorize('create', Employee::class);

        return response()->json(['data' => [
            'import' => $import->uuid,
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'imported_rows' => $import->imported_rows,
            'failed_rows' => $import->failed_rows,
            'errors' => $import->errors ?? [],
        ]]);
    }
}
