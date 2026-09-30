<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatutoryReturnType;
use App\Models\PayrollRun;
use App\Services\Payroll\StatutoryReturns\StatutoryReturnBuilder;
use App\Services\Payroll\StatutoryReturns\StatutoryReturnExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StatutoryReturnController extends Controller
{
    public function __construct(
        private readonly StatutoryReturnBuilder $builder,
        private readonly StatutoryReturnExporter $exporter,
    ) {}

    /**
     * Validation report: identifier problems that would make a portal upload fail.
     */
    public function show(PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('view', $payrollRun);

        $built = $this->builder->build($payrollRun);

        return response()->json(['data' => [
            'employees' => count($built['rows']),
            'issues' => $built['issues'],
        ]]);
    }

    public function download(Request $request, PayrollRun $payrollRun, string $type): Response
    {
        $this->authorize('view', $payrollRun);

        $returnType = $type === 'all' ? null : StatutoryReturnType::tryFrom($type);
        abort_if($type !== 'all' && $returnType === null, 404);

        $built = $this->builder->build($payrollRun);
        $checked = $returnType === null ? $built['issues'] : [$returnType->value => $built['issues'][$returnType->value]];
        $issues = array_filter($checked);

        if ($issues !== [] && ! $request->boolean('force')) {
            return response()->json([
                'message' => 'Fix the listed employee details first, or repeat the request with force=1 to export anyway.',
                'issues' => $issues,
            ], 422);
        }

        if ($returnType === null) {
            return response()->download($this->exporter->bundle($payrollRun), "statutory-returns-{$built['name']}.zip")
                ->deleteFileAfterSend();
        }

        $file = $this->exporter->file($payrollRun, $returnType, $built);

        return response($file['contents'], 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
        ]);
    }
}
