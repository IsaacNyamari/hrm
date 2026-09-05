<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\Employee;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ShiftController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Shift::class);

        return response()->json([
            'data' => Shift::query()->orderBy('name')->get()->map(fn (Shift $s): array => $this->transform($s)),
        ]);
    }

    public function show(Shift $shift): JsonResponse
    {
        $this->authorize('view', $shift);

        return response()->json(['data' => $this->transform($shift)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Shift::class);

        $data = $this->validated($request);
        $shift = Shift::query()->create($data);

        return response()->json(['data' => $this->transform($shift)], 201);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $this->authorize('update', $shift);

        $shift->update($this->validated($request, $shift));

        return response()->json(['data' => $this->transform($shift)]);
    }

    public function destroy(Shift $shift): Response
    {
        $this->authorize('delete', $shift);

        abort_if($shift->assignments()->exists(), 409, 'Reassign employees before deleting this shift.');
        $shift->delete();

        return response()->noContent();
    }

    public function assign(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('create', Shift::class);

        $data = $request->validate([
            'shift_id' => ['required', Rule::exists('shifts', 'id')->where('tenant_id', Tenancy::id())],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $from = CarbonImmutable::parse($data['effective_from']);

        $employee->shiftAssignments()
            ->whereNull('effective_to')
            ->whereDate('effective_from', '<', $from->toDateString())
            ->update(['effective_to' => $from->subDay()->toDateString()]);

        $assignment = $employee->shiftAssignments()->create([
            'tenant_id' => Tenancy::id(),
            'shift_id' => $data['shift_id'],
            'effective_from' => $from->toDateString(),
            'effective_to' => $data['effective_to'] ?? null,
        ]);

        return response()->json(['data' => [
            'employee' => $employee->uuid,
            'shift' => $assignment->shift->uuid,
            'effective_from' => $assignment->effective_from->toDateString(),
        ]], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Shift $shift = null): array
    {
        $required = $shift === null ? 'required' : 'sometimes';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'start_time' => [$required, 'date_format:H:i'],
            'end_time' => [$required, 'date_format:H:i'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'working_days' => [$required, 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7'],
            'overtime_after_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        if (isset($data['start_time'], $data['end_time'])) {
            $data['is_night_shift'] = $data['end_time'] <= $data['start_time'];
        }

        if (($data['is_default'] ?? false) === true) {
            Shift::query()->where('is_default', true)->update(['is_default' => false]);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Shift $shift): array
    {
        return [
            'uuid' => $shift->uuid,
            'name' => $shift->name,
            'start_time' => substr($shift->start_time, 0, 5),
            'end_time' => substr($shift->end_time, 0, 5),
            'break_minutes' => $shift->break_minutes,
            'grace_minutes' => $shift->grace_minutes,
            'working_days' => $shift->working_days,
            'scheduled_minutes' => $shift->scheduledMinutes(),
            'is_night_shift' => $shift->is_night_shift,
            'is_default' => $shift->is_default,
            'is_active' => $shift->is_active,
        ];
    }
}
