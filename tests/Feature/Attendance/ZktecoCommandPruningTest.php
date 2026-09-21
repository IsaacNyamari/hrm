<?php

declare(strict_types=1);

use Athwari\LaravelZktecoAdms\Models\ZktecoDeviceCommand;

it('prunes finished commands past the retention window but keeps recent and unfinished ones', function () {
    config(['zkteco-adms.command_pruning_enabled' => true, 'zkteco-adms.command_retention_days' => 30]);

    $oldAcknowledged = ZktecoDeviceCommand::factory()->acknowledged()->create(['queued_at' => now()->subDays(45)]);
    $oldFailed = ZktecoDeviceCommand::factory()->failed()->create(['queued_at' => now()->subDays(45)]);
    $recentAcknowledged = ZktecoDeviceCommand::factory()->acknowledged()->create(['queued_at' => now()->subDays(2)]);
    $oldPending = ZktecoDeviceCommand::factory()->pending()->create(['queued_at' => now()->subDays(45)]);

    $this->artisan('model:prune', ['--model' => [ZktecoDeviceCommand::class]]);

    expect(ZktecoDeviceCommand::query()->find($oldAcknowledged->id))->toBeNull()
        ->and(ZktecoDeviceCommand::query()->find($oldFailed->id))->toBeNull()
        ->and(ZktecoDeviceCommand::query()->find($recentAcknowledged->id))->not->toBeNull()
        ->and(ZktecoDeviceCommand::query()->find($oldPending->id))->not->toBeNull();
});

it('prunes nothing when command pruning is disabled', function () {
    config(['zkteco-adms.command_pruning_enabled' => false]);

    $oldAcknowledged = ZktecoDeviceCommand::factory()->acknowledged()->create(['queued_at' => now()->subDays(90)]);

    $this->artisan('model:prune', ['--model' => [ZktecoDeviceCommand::class]]);

    expect(ZktecoDeviceCommand::query()->find($oldAcknowledged->id))->not->toBeNull();
});
