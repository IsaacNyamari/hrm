<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PayrollFrequency;
use App\Models\PayrollPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollPeriodController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        if (! $request->wantsJson()) {
            return view('payroll.index');
        }

        return response()->json([
            'data' => PayrollPeriod::query()->with('runs:id,payroll_period_id,status,net_cents,employee_count')
                ->orderByDesc('start_date')->get()->map(fn (PayrollPeriod $p): array => $this->transform($p)),
        ]);
    }

    public function show(PayrollPeriod $payrollPeriod): JsonResponse
    {
        $this->authorize('view', $payrollPeriod);

        return response()->json(['data' => $this->transform($payrollPeriod->load('runs'))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'frequency' => ['required', new Enum(PayrollFrequency::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pay_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $duplicate = PayrollPeriod::query()->regular()
            ->whereDate('start_date', $data['start_date'])
            ->whereDate('end_date', $data['end_date'])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['start_date' => __('A payroll period already covers these dates.')]);
        }

        $period = PayrollPeriod::query()->create($data);

        return response()->json(['data' => $this->transform($period->fresh())], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(PayrollPeriod $p): array
    {
        return [
            'uuid' => $p->uuid,
            'name' => $p->name,
            'frequency' => $p->frequency->value,
            'start_date' => $p->start_date->toDateString(),
            'end_date' => $p->end_date->toDateString(),
            'pay_date' => $p->pay_date->toDateString(),
            'status' => $p->status->value,
            'runs' => $p->relationLoaded('runs') ? $p->runs->map(fn ($r): array => [
                'uuid' => $r->uuid ?? null,
                'status' => $r->status->value,
                'net_cents' => $r->net_cents,
                'employee_count' => $r->employee_count,
            ])->all() : null,
        ];
    }
}
