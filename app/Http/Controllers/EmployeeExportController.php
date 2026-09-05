<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\EmployeesExport;
use App\Models\Employee;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Employee::class);

        $format = $request->string('format', 'xlsx')->lower()->toString();
        $writer = $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX;

        return Excel::download(
            new EmployeesExport($request->string('search')->toString() ?: null),
            "employees.{$format}",
            $writer,
        );
    }
}
