<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AttendanceSource;
use App\Exceptions\AttendanceException;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Models\Scopes\TenantScope;
use App\Services\Attendance\AttendanceService;
use App\Support\Attendance\PunchData;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ingestion endpoint for registered attendance devices. Auth is the device's
 * shared secret (header `X-Device-Secret`). The app never talks to hardware —
 * any device type normalises its punch to this contract.
 */
class AttendanceDeviceWebhookController extends Controller
{
    public function __invoke(Request $request, string $device, AttendanceService $service, TenantContext $context): JsonResponse
    {
        $model = AttendanceDevice::query()->withoutGlobalScope(TenantScope::class)->where('uuid', $device)->first();

        if ($model === null || ! $model->is_active
            || ! hash_equals($model->secret, (string) $request->header('X-Device-Secret'))) {
            return response()->json(['status' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'employee_number' => ['required_without:employee_uuid', 'string'],
            'employee_uuid' => ['required_without:employee_number', 'string'],
            'event' => ['required', 'in:in,out,break_start,break_end'],
            'at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $employee = Employee::query()->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $model->tenant_id)
            ->when(isset($data['employee_number']), fn ($q) => $q->where('employee_number', $data['employee_number']))
            ->when(isset($data['employee_uuid']), fn ($q) => $q->where('uuid', $data['employee_uuid']))
            ->first();

        if ($employee === null) {
            return response()->json(['status' => 'rejected', 'message' => 'Unknown employee.'], 422);
        }

        $model->forceFill(['last_seen_at' => CarbonImmutable::now()])->save();

        $punch = new PunchData(
            source: AttendanceSource::Biometric,
            at: isset($data['at']) ? CarbonImmutable::parse($data['at']) : null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            location: $model->name,
        );

        try {
            $context->run($model->tenant, fn () => match ((string) $data['event']) {
                'in' => $service->clockIn($employee, $punch),
                'out' => $service->clockOut($employee, $punch),
                'break_start' => $service->startBreak($employee, $punch),
                'break_end' => $service->endBreak($employee, $punch),
                default => null,
            });
        } catch (AttendanceException $e) {
            return response()->json(['status' => 'rejected', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'ok']);
    }
}
