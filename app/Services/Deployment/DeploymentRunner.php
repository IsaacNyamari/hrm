<?php

declare(strict_types=1);

namespace App\Services\Deployment;

use App\Enums\DeploymentStatus;
use App\Enums\DeploymentType;
use App\Models\Deployment;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Runs the git/composer/npm/artisan pipeline for a deployment record, against
 * this application's own working copy (base_path()) — whatever server this
 * code happens to be running on. Every pipeline command is invoked as an
 * argument array, never a shell string, so nothing here is vulnerable to
 * injection; the one exception is the operator's own configured post-deploy
 * commands (config('deployment.post_deploy_commands')), which are trusted
 * shell strings the server operator wrote themselves into .env.
 *
 * The whole run is wrapped in maintenance mode: this is a plain in-place git
 * deploy, not a zero-downtime symlink-swap, so the code on disk is briefly
 * inconsistent while it runs.
 */
class DeploymentRunner
{
    private string $log = '';

    public function run(Deployment $deployment): void
    {
        $deployment->forceFill(['status' => DeploymentStatus::Running, 'started_at' => now()])->save();

        $this->maintenanceMode(true);

        try {
            match ($deployment->type) {
                DeploymentType::Pull => $this->pull($deployment),
                DeploymentType::Rollback => $this->rollback($deployment),
            };

            $this->runPipelineTail();

            $deployment->forceFill([
                'status' => DeploymentStatus::Succeeded,
                'to_commit' => $this->currentCommit(),
                'output' => $this->log,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $this->append("\n! ".$e->getMessage());

            $deployment->forceFill([
                'status' => DeploymentStatus::Failed,
                'output' => $this->log,
                'finished_at' => now(),
            ])->save();
        } finally {
            $this->maintenanceMode(false);
        }
    }

    public function currentCommit(): string
    {
        return trim($this->step(['git', 'rev-parse', 'HEAD'])->output());
    }

    public function currentBranch(): string
    {
        return trim($this->step(['git', 'rev-parse', '--abbrev-ref', 'HEAD'])->output());
    }

    /**
     * @return array<int, array{sha: string, date: string, message: string}>
     */
    public function recentCommits(int $limit = 20): array
    {
        $log = $this->step(['git', 'log', '-n', (string) $limit, '--pretty=format:%H|%aI|%s'])->output();

        $commits = [];

        foreach (array_filter(explode("\n", trim($log))) as $line) {
            [$sha, $date, $message] = array_pad(explode('|', $line, 3), 3, '');
            $commits[] = ['sha' => $sha, 'date' => $date, 'message' => $message];
        }

        return $commits;
    }

    private function pull(Deployment $deployment): void
    {
        $remote = (string) config('deployment.remote', 'origin');
        $branch = (string) $deployment->branch;

        $this->step(['git', 'fetch', $remote, $branch]);
        $this->step(['git', 'reset', '--hard', "{$remote}/{$branch}"]);
    }

    private function rollback(Deployment $deployment): void
    {
        $this->step(['git', 'reset', '--hard', (string) $deployment->to_commit]);
    }

    private function runPipelineTail(): void
    {
        $this->step(['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction']);

        if (config('deployment.run_npm_build', true)) {
            $this->step(['npm', 'ci', '--no-audit', '--no-fund']);
            $this->step(['npm', 'run', 'build']);
        }

        $this->step(['php', 'artisan', 'migrate', '--force']);
        $this->step(['php', 'artisan', 'config:cache']);
        $this->step(['php', 'artisan', 'route:cache']);
        $this->step(['php', 'artisan', 'view:cache']);
        $this->step(['php', 'artisan', 'event:cache']);
        $this->step(['php', 'artisan', 'queue:restart']);

        foreach (config('deployment.post_deploy_commands', []) as $command) {
            $this->append("\n$ {$command}\n");
            $result = Process::path(base_path())->timeout((int) config('deployment.command_timeout', 300))->run($command);
            $this->append($result->output().$result->errorOutput());
        }
    }

    private function step(array $command): ProcessResult
    {
        $this->append("\n$ ".implode(' ', $command)."\n");

        $result = Process::path(base_path())->timeout((int) config('deployment.command_timeout', 300))->run($command);

        $this->append($result->output().$result->errorOutput());

        if (! $result->successful()) {
            throw new RuntimeException('Command failed (exit '.$result->exitCode().'): '.implode(' ', $command));
        }

        return $result;
    }

    /**
     * Skipped during automated tests — this writes a real
     * storage/framework/down file, which must never happen outside an actual
     * deployment run.
     */
    private function maintenanceMode(bool $down): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $down
            ? Artisan::call('down', ['--render' => 'errors::503'])
            : Artisan::call('up');
    }

    private function append(string $text): void
    {
        $this->log .= $text;

        if (mb_strlen($this->log) > 50000) {
            $this->log = '…(truncated)…'.mb_substr($this->log, -50000);
        }
    }
}
