<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AttendanceDeviceType;
use App\Facades\Tenancy;
use App\Models\AttendanceDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class AttendanceDeviceController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', AttendanceDevice::class);

        return response()->json([
            'data' => AttendanceDevice::query()->with('branch:id,name')->orderBy('name')->get()
                ->map(fn (AttendanceDevice $d): array => $this->transform($d)),
        ]);
    }

    public function show(AttendanceDevice $device): JsonResponse
    {
        $this->authorize('view', $device);

        return response()->json(['data' => $this->transform($device)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AttendanceDevice::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', new Enum(AttendanceDeviceType::class)],
            'serial_number' => [
                'nullable', 'string', 'max:120',
                Rule::unique('attendance_devices', 'serial_number')->where('tenant_id', Tenancy::id()),
            ],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', Tenancy::id())],
        ]);

        $secret = Str::random(48);
        $device = AttendanceDevice::query()->create($data + ['secret' => $secret]);

        // Secret is shown exactly once, at registration.
        return response()->json([
            'data' => $this->transform($device) + [
                'secret' => $secret,
                'webhook_url' => route('webhooks.attendance', ['device' => $device->uuid]),
            ],
        ], 201);
    }

    public function update(Request $request, AttendanceDevice $device): JsonResponse
    {
        $this->authorize('update', $device);

        $device->update($request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', Tenancy::id())],
            'is_active' => ['boolean'],
        ]));

        return response()->json(['data' => $this->transform($device)]);
    }

    public function destroy(AttendanceDevice $device): Response
    {
        $this->authorize('delete', $device);
        $device->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(AttendanceDevice $device): array
    {
        return [
            'uuid' => $device->uuid,
            'name' => $device->name,
            'type' => $device->type->value,
            'serial_number' => $device->serial_number,
            'branch' => $device->branch?->name,
            'is_active' => $device->is_active,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ];
    }
}
