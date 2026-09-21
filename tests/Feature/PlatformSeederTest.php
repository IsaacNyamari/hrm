<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Illuminate\Support\Facades\Hash;

it('creates the platform super admin from the configured credentials', function () {
    config([
        'platform.admin.email' => 'owner@example.com',
        'platform.admin.password' => 'S3cure-pass!',
    ]);

    $this->seed(PlatformSeeder::class);

    $admin = User::query()->where('email', 'owner@example.com')->firstOrFail();

    expect($admin->is_platform_admin)->toBeTrue()
        ->and(Hash::check('S3cure-pass!', $admin->password))->toBeTrue()
        ->and(User::query()->where('email', 'superadmin@hrm.test')->exists())->toBeFalse();
});
