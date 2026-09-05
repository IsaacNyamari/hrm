<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Subscription\SubscriptionManager;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire trials and past-due subscriptions whose grace period has elapsed';

    public function handle(SubscriptionManager $subscriptions): int
    {
        $count = $subscriptions->expireOverdue();

        $this->info("Expired {$count} subscription(s).");

        return self::SUCCESS;
    }
}
