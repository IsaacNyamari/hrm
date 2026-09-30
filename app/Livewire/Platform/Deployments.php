<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Enums\DeploymentStatus;
use App\Enums\DeploymentType;
use App\Enums\PlatformPermission;
use App\Jobs\RunDeployment;
use App\Livewire\Concerns\BindsTenant;
use App\Livewire\Platform\Concerns\WithPlatformGuard;
use App\Models\Deployment;
use App\Services\Deployment\DeploymentRunner;
use App\Support\Auditor;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Lets the platform admin pull the latest git commit or roll back to a
 * previous one, running the actual pipeline in the background (App\Jobs\RunDeployment)
 * so the click isn't held to an HTTP timeout. This page polls while a
 * deployment is running rather than pushing updates over a channel — deploys
 * are rare enough that the extra plumbing isn't worth it.
 */
class Deployments extends Component
{
    use BindsTenant;
    use WithPlatformGuard;

    public function mount(): void
    {
        $this->authorizePlatform(PlatformPermission::DeploymentsManage);
    }

    public function pullLatest(DeploymentRunner $runner, Auditor $auditor): void
    {
        $this->authorizePlatform(PlatformPermission::DeploymentsManage);
        $this->guardNoneRunning();

        $deployment = Deployment::query()->create([
            'type' => DeploymentType::Pull,
            'status' => DeploymentStatus::Running,
            'branch' => $runner->currentBranch(),
            'from_commit' => $runner->currentCommit(),
            'triggered_by' => auth()->id(),
            'started_at' => now(),
        ]);

        $auditor->log('platform.deployment.pull_started', $deployment, ['from_commit' => $deployment->from_commit]);

        RunDeployment::dispatch($deployment);
    }

    public function rollback(string $sha, DeploymentRunner $runner, Auditor $auditor): void
    {
        $this->authorizePlatform(PlatformPermission::DeploymentsManage);
        $this->guardNoneRunning();

        abort_unless((bool) preg_match('/^[0-9a-f]{7,40}$/i', $sha), 422);

        $deployment = Deployment::query()->create([
            'type' => DeploymentType::Rollback,
            'status' => DeploymentStatus::Running,
            'branch' => $runner->currentBranch(),
            'from_commit' => $runner->currentCommit(),
            'to_commit' => $sha,
            'triggered_by' => auth()->id(),
            'started_at' => now(),
        ]);

        $auditor->log('platform.deployment.rollback_started', $deployment, ['from_commit' => $deployment->from_commit, 'to_commit' => $sha]);

        RunDeployment::dispatch($deployment);
    }

    private function guardNoneRunning(): void
    {
        abort_if(Deployment::query()->where('status', DeploymentStatus::Running)->exists(), 409);
    }

    public function render(DeploymentRunner $runner): View
    {
        return view('livewire.platform.deployments', [
            'currentCommit' => rescue(fn () => $runner->currentCommit(), 'unknown', false),
            'currentBranch' => rescue(fn () => $runner->currentBranch(), 'unknown', false),
            'recentCommits' => rescue(fn () => $runner->recentCommits(20), [], false),
            'deployments' => Deployment::query()->with('triggeredBy:id,name')->latest('id')->limit(20)->get(),
            'hasRunning' => Deployment::query()->where('status', DeploymentStatus::Running)->exists(),
        ]);
    }
}
