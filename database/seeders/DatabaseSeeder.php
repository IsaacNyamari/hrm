<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Note: model events are intentionally left enabled so the HasUuid /
     * BelongsToTenant creating hooks run during seeding.
     */
    public function run(): void
    {
        $this->call([
            PlatformSeeder::class,
            DemoTenantSeeder::class,
        ]);
    }
}
