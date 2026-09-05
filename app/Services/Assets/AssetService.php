<?php

declare(strict_types=1);

namespace App\Services\Assets;

use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetStatus;
use App\Exceptions\AssetException;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AssetService
{
    /**
     * @param  array{assigned_on?: string|null, due_back_on?: string|null, condition_out?: string|null, notes?: string|null}  $data
     */
    public function assign(Asset $asset, Employee $employee, array $data = [], ?User $actor = null): AssetAssignment
    {
        if (! $asset->status->isAssignable()) {
            throw AssetException::notAvailable();
        }

        if ($asset->assignments()->where('status', AssetAssignmentStatus::Active->value)->exists()) {
            throw AssetException::alreadyAssigned();
        }

        return DB::transaction(function () use ($asset, $employee, $data, $actor): AssetAssignment {
            $assignment = $asset->assignments()->create([
                'tenant_id' => $asset->tenant_id,
                'employee_id' => $employee->getKey(),
                'assigned_on' => $data['assigned_on'] ?? CarbonImmutable::now()->toDateString(),
                'due_back_on' => $data['due_back_on'] ?? null,
                'status' => AssetAssignmentStatus::Active,
                'condition_out' => $data['condition_out'] ?? null,
                'notes' => $data['notes'] ?? null,
                'assigned_by' => $actor?->getKey(),
            ]);

            $asset->forceFill(['status' => AssetStatus::Assigned])->save();

            return $assignment;
        });
    }

    /**
     * @param  array{returned_on?: string|null, condition_in?: string|null, notes?: string|null, status?: string}  $data
     */
    public function checkIn(AssetAssignment $assignment, array $data = []): AssetAssignment
    {
        if ($assignment->status !== AssetAssignmentStatus::Active) {
            throw AssetException::notActive();
        }

        return DB::transaction(function () use ($assignment, $data): AssetAssignment {
            $assignment->forceFill([
                'status' => AssetAssignmentStatus::Returned,
                'returned_on' => $data['returned_on'] ?? CarbonImmutable::now()->toDateString(),
                'condition_in' => $data['condition_in'] ?? null,
                'notes' => $data['notes'] ?? $assignment->notes,
            ])->save();

            $newStatus = ($data['status'] ?? null) === 'in_repair' ? AssetStatus::InRepair : AssetStatus::Available;
            $assignment->asset->forceFill(['status' => $newStatus])->save();

            return $assignment;
        });
    }

    public function retire(Asset $asset, AssetStatus $status = AssetStatus::Retired): Asset
    {
        if ($asset->assignments()->where('status', AssetAssignmentStatus::Active->value)->exists()) {
            throw AssetException::hasActiveAssignment();
        }

        $asset->forceFill(['status' => $status])->save();

        return $asset;
    }
}
