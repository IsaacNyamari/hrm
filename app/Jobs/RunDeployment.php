<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\Deployment\DeploymentRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs a queued deployment in the background so the platform admin's click
 * isn't held to an HTTP request timeout while composer/npm/migrate run.
 * Deliberately not retried on failure — an operator should read the captured
 * output and decide the next step rather than have a broken deploy retried
 * automatically.
 */
class RunDeployment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly Deployment $deployment) {}

    public function handle(DeploymentRunner $runner): void
    {
        $runner->run($this->deployment);
    }
}
