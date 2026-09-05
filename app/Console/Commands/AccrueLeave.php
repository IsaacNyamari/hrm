<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Leave\LeaveService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class AccrueLeave extends Command
{
    protected $signature = 'leave:accrue {--year= : Leave year to accrue for (defaults to the current leave year)}';

    protected $description = 'Grant / refresh leave allocations and carry-forward for every tenant';

    public function handle(LeaveService $service, TenantContext $context): int
    {
        $year = $this->option('year') !== null ? (int) $this->option('year') : null;
        $total = 0;

        Tenant::query()->chunkById(100, function ($tenants) use ($service, $context, $year, &$total): void {
            foreach ($tenants as $tenant) {
                $total += $context->run($tenant, fn (): int => $service->accrue($year));
            }
        });

        $this->info("Accrued {$total} leave balance(s).");

        return self::SUCCESS;
    }
}
