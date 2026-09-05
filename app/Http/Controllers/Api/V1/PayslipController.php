<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PayslipResource;
use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PayslipController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $payslips = Payslip::query()
            ->with('run.period:id,name')
            ->when($request->filled('employee_uuid'), fn ($q) => $q->whereRelation('employee', 'uuid', $request->string('employee_uuid')->toString()))
            ->when($request->filled('run_uuid'), fn ($q) => $q->whereRelation('run', 'uuid', $request->string('run_uuid')->toString()))
            ->latest('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        return PayslipResource::collection($payslips);
    }

    public function show(string $payslip): PayslipResource
    {
        $record = Payslip::query()
            ->with(['run.period:id,name', 'lines'])
            ->where('uuid', $payslip)
            ->firstOrFail();

        return new PayslipResource($record);
    }
}
