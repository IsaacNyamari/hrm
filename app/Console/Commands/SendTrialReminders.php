<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Scopes\TenantScope;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\TrialEndingNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendTrialReminders extends Command
{
    protected $signature = 'subscriptions:send-reminders';

    protected $description = 'Notify tenants whose trial ends in a configured number of days';

    public function handle(): int
    {
        /** @var array<int, int> $marks */
        $marks = config('billing.trial_reminder_days', [7, 3, 1]);
        $today = CarbonImmutable::now()->startOfDay();
        $sent = 0;

        Subscription::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('status', SubscriptionStatus::Trialing->value)
            ->whereNotNull('trial_ends_at')
            ->with('tenant')
            ->chunkById(200, function ($subscriptions) use ($marks, $today, &$sent): void {
                foreach ($subscriptions as $subscription) {
                    $daysLeft = (int) $today->diffInDays($subscription->trial_ends_at->startOfDay(), false);

                    if (! in_array($daysLeft, $marks, true)) {
                        continue;
                    }

                    $recipients = User::query()->where('tenant_id', $subscription->tenant_id)->get();
                    Notification::send($recipients, new TrialEndingNotification($subscription, $daysLeft));
                    $sent += $recipients->count();
                }
            });

        $this->info("Sent {$sent} trial reminder(s).");

        return self::SUCCESS;
    }
}
