<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentExpiryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EmployeeDocument::class);

        abort_unless($request->user()?->can('employees.documents.view'), 403);

        $days = min(365, max(1, $request->integer('days', 60)));
        $today = CarbonImmutable::now();

        $documents = EmployeeDocument::query()
            ->with('employee:id,first_name,last_name,employee_number')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $today->toDateString())
            ->whereDate('expiry_date', '<=', $today->addDays($days)->toDateString())
            ->orderBy('expiry_date')
            ->get();

        return response()->json(['data' => $documents->map(fn (EmployeeDocument $d): array => [
            'uuid' => $d->uuid,
            'title' => $d->title,
            'category' => $d->category->value,
            'employee' => $d->employee->fullName(),
            'employee_number' => $d->employee->employee_number,
            'expiry_date' => $d->expiry_date?->toDateString(),
            'days_left' => (int) $today->startOfDay()->diffInDays($d->expiry_date->startOfDay(), false),
        ])]);
    }
}
