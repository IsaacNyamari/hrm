<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Models\EmployeeDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\DocumentExpiringNotification;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

class CheckDocumentExpiry extends Command
{
    protected $signature = 'documents:check-expiry';

    protected $description = 'Notify about employee documents approaching their expiry date';

    public function handle(TenantContext $context): int
    {
        $today = CarbonImmutable::now()->startOfDay();
        $sent = 0;

        Tenant::query()->each(function (Tenant $tenant) use ($context, $today, &$sent): void {
            $context->run($tenant, function () use ($tenant, $today, &$sent): void {
                $hrUsers = $this->hrRecipients($tenant->getKey());

                EmployeeDocument::query()
                    ->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '>=', $today->toDateString())
                    ->with(['employee.user', 'documentType'])
                    ->chunkById(200, function ($documents) use ($today, $hrUsers, &$sent): void {
                        foreach ($documents as $document) {
                            $daysLeft = (int) $today->diffInDays($document->expiry_date->startOfDay(), false);
                            $marks = $document->documentType?->reminderMarks() ?? [30, 14, 7];

                            if (! in_array($daysLeft, $marks, true)) {
                                continue;
                            }

                            $alreadySent = $document->expiry_reminders_sent ?? [];
                            if (in_array($daysLeft, $alreadySent, true)) {
                                continue;
                            }

                            $recipients = $document->employee->user !== null
                                ? $hrUsers->merge([$document->employee->user])->unique('id')
                                : $hrUsers;

                            Notification::send($recipients, new DocumentExpiringNotification($document, $daysLeft));

                            $document->forceFill(['expiry_reminders_sent' => [...$alreadySent, $daysLeft]])->save();
                            $sent += $recipients->count();
                        }
                    });
            });
        });

        $this->info("Sent {$sent} document-expiry reminder(s).");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    private function hrRecipients(int $tenantId): Collection
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenantId);

        try {
            return User::query()
                ->where('tenant_id', $tenantId)
                ->permission(Permission::EmployeesDocumentsView->value)
                ->get();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
