<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmployeeImport;
use App\Models\Scopes\TenantScope;
use App\Services\Employees\EmployeeImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessEmployeeImport implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $importId) {}

    public function handle(EmployeeImporter $importer): void
    {
        $import = EmployeeImport::query()->withoutGlobalScope(TenantScope::class)->find($this->importId);

        if ($import === null || $import->status !== EmployeeImport::STATUS_PENDING) {
            return;
        }

        $importer->run($import);
    }

    public function failed(\Throwable $exception): void
    {
        EmployeeImport::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('id', $this->importId)
            ->update([
                'status' => EmployeeImport::STATUS_FAILED,
                'errors' => [['row' => 0, 'messages' => [$exception->getMessage()]]],
                'completed_at' => now(),
            ]);
    }
}
