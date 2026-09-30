<?php

declare(strict_types=1);

use App\Enums\DeploymentStatus;
use App\Enums\DeploymentType;
use App\Enums\Role;
use App\Livewire\Platform\Deployments;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\User;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

/**
 * Process::fake()'s string-pattern keys match against a rebuilt command line,
 * which is escaped differently per OS — unreliable for array-built commands
 * in a cross-platform test. Inspecting $process->command directly sidesteps
 * that entirely.
 */
function fakeGitCommands(array $overrides = []): void
{
    $head = 'aaaaaaa1111111111111111111111111111111';

    Process::fake(function (PendingProcess $process) use ($overrides, &$head) {
        $command = (array) $process->command;
        $line = implode(' ', $command);

        foreach ($overrides as $prefix => $result) {
            if (str_starts_with($line, $prefix)) {
                return $result;
            }
        }

        // A real `git reset --hard <ref>` moves HEAD; mirror that so a
        // subsequent `git rev-parse HEAD` in the same pipeline reflects it.
        if (str_starts_with($line, 'git reset --hard') && preg_match('/^[0-9a-f]{7,40}$/i', (string) end($command))) {
            $head = end($command);
        }

        return match (true) {
            str_starts_with($line, 'git rev-parse HEAD') => Process::result(output: $head."\n"),
            str_starts_with($line, 'git rev-parse --abbrev-ref HEAD') => Process::result(output: "master\n"),
            str_starts_with($line, 'git log') => Process::result(output: "aaaaaaa1111111111111111111111111111111|2026-01-01T00:00:00+00:00|Initial commit\nbbbbbbb2222222222222222222222222222222|2025-12-31T00:00:00+00:00|Older commit\n"),
            default => Process::result(output: "ok\n"),
        };
    });
}

beforeEach(function () {
    fakeGitCommands();
});

it('lets a super admin pull the latest changes and records a succeeded deployment', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    Livewire::test(Deployments::class)
        ->assertSee('master')
        ->call('pullLatest');

    $deployment = Deployment::query()->latest('id')->first();

    expect($deployment->type)->toBe(DeploymentType::Pull)
        ->and($deployment->status)->toBe(DeploymentStatus::Succeeded)
        ->and($deployment->branch)->toBe('master')
        ->and($deployment->output)->toContain('git fetch');

    expect(AuditLog::query()->where('event', 'platform.deployment.pull_started')->exists())->toBeTrue();
});

it('rolls back to a chosen commit', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    Livewire::test(Deployments::class)
        ->call('rollback', 'bbbbbbb2222222222222222222222222222222');

    $deployment = Deployment::query()->latest('id')->first();

    expect($deployment->type)->toBe(DeploymentType::Rollback)
        ->and($deployment->status)->toBe(DeploymentStatus::Succeeded)
        ->and($deployment->to_commit)->toBe('bbbbbbb2222222222222222222222222222222');
});

it('shows a readable error instead of crashing when git itself refuses to run', function () {
    fakeGitCommands([
        'git rev-parse' => Process::result(errorOutput: "fatal: detected dubious ownership in repository at '/var/www/hrm'", exitCode: 128),
    ]);

    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    Livewire::test(Deployments::class)->call('pullLatest');

    expect(Deployment::query()->count())->toBe(0);
});

it('self-heals a dubious-ownership git error and completes the deployment', function () {
    $safeDirectoryAdded = false;

    Process::fake(function (PendingProcess $process) use (&$safeDirectoryAdded) {
        $command = (array) $process->command;
        $line = implode(' ', $command);

        if (str_starts_with($line, 'git config --global --add safe.directory')) {
            $safeDirectoryAdded = true;

            return Process::result(output: '');
        }

        if (! $safeDirectoryAdded && ($command[0] ?? null) === 'git') {
            return Process::result(errorOutput: "fatal: detected dubious ownership in repository at '/var/www/hrm'", exitCode: 128);
        }

        return match (true) {
            str_starts_with($line, 'git rev-parse HEAD') => Process::result(output: "aaaaaaa1111111111111111111111111111111\n"),
            str_starts_with($line, 'git rev-parse --abbrev-ref HEAD') => Process::result(output: "master\n"),
            default => Process::result(output: "ok\n"),
        };
    });

    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    Livewire::test(Deployments::class)->call('pullLatest');

    $deployment = Deployment::query()->latest('id')->first();

    // The safe.directory fix-and-retry happens inside a throwaway git-info
    // check before the deployment record even exists (see Deployments::readGitInfo()),
    // so what matters here is that the deployment goes on to succeed anyway,
    // not that this specific record's log mentions it.
    expect($deployment)->not->toBeNull()
        ->and($deployment->status)->toBe(DeploymentStatus::Succeeded)
        ->and($safeDirectoryAdded)->toBeTrue();
});

it('records a failed deployment when a pipeline step fails', function () {
    fakeGitCommands([
        'composer install' => Process::result(errorOutput: 'composer exploded', exitCode: 1),
    ]);

    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    Livewire::test(Deployments::class)->call('pullLatest');

    $deployment = Deployment::query()->latest('id')->first();

    expect($deployment->status)->toBe(DeploymentStatus::Failed)
        ->and($deployment->output)->toContain('composer exploded');
});

it('refuses to start a second deployment while one is running', function () {
    Deployment::factory()->running()->create();

    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    Livewire::test(Deployments::class)
        ->call('pullLatest')
        ->assertStatus(409);
});

it('validates the rollback target looks like a commit sha', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    Livewire::test(Deployments::class)
        ->call('rollback', 'not-a-sha; rm -rf /')
        ->assertStatus(422);
});

it('forbids a platform role without deployments.manage', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SupportAdmin)->create())
        ->get(route('platform.deployments'))
        ->assertForbidden();
});
