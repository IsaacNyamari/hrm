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
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

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

        $gitInfo = $this->readGitInfo($runner);

        if ($gitInfo === null) {
            return;
        }

        $deployment = Deployment::query()->create([
            'type' => DeploymentType::Pull,
            'status' => DeploymentStatus::Running,
            'branch' => $gitInfo['branch'],
            'from_commit' => $gitInfo['commit'],
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

        $gitInfo = $this->readGitInfo($runner);

        if ($gitInfo === null) {
            return;
        }

        $deployment = Deployment::query()->create([
            'type' => DeploymentType::Rollback,
            'status' => DeploymentStatus::Running,
            'branch' => $gitInfo['branch'],
            'from_commit' => $gitInfo['commit'],
            'to_commit' => $sha,
            'triggered_by' => auth()->id(),
            'started_at' => now(),
        ]);

        $auditor->log('platform.deployment.rollback_started', $deployment, ['from_commit' => $deployment->from_commit, 'to_commit' => $sha]);

        RunDeployment::dispatch($deployment);
    }

    /**
     * Read the current branch/commit before creating a deployment record. A
     * failure here (most commonly git refusing to run at all — e.g. "detected
     * dubious ownership in repository" when the web/queue user doesn't own
     * the working copy) would otherwise surface as a raw 500 instead of a
     * readable message telling the operator what to fix on the server.
     *
     * @return array{branch: string, commit: string}|null
     */
    private function readGitInfo(DeploymentRunner $runner): ?array
    {
        try {
            return ['branch' => $runner->currentBranch(), 'commit' => $runner->currentCommit()];
        } catch (Throwable $e) {
            Flux::toast(variant: 'danger', text: __('Could not read the git repository: :message', ['message' => $e->getMessage()]));

            return null;
        }
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
