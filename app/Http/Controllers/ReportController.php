<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Reports\Report;
use App\Reports\ReportRegistry;
use App\Services\Reporting\ReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $service,
        private readonly ReportRegistry $registry,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $reports = collect($this->registry->availableFor($user))
            ->map(fn (Report $report): array => [
                'key' => $report->key(),
                'name' => $report->name(),
                'category' => $report->category(),
                'filters' => $report->filters(),
                'columns' => $report->columns(),
            ])
            ->values();

        return response()->json(['data' => $reports]);
    }

    public function show(Request $request, string $report): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return response()->json(['data' => $this->service->run($report, $request->query(), $user)]);
    }

    public function export(Request $request, string $report): BinaryFileResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless($user->can('reports.export'), 403);

        $result = $this->service->run($report, $request->query(), $user);

        $format = $request->string('format', 'xlsx')->lower()->toString();
        $writer = $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX;
        $ext = $format === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            new ReportExport($result['name'], $result['columns'], $result['rows']),
            "{$report}.{$ext}",
            $writer,
        );
    }

    public function print(Request $request, string $report): View
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return view('reports.print', ['result' => $this->service->run($report, $request->query(), $user)]);
    }
}
