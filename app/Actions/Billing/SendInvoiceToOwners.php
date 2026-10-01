<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Role;
use App\Facades\Tenancy;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceSentNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Emails an invoice (PDF attached) to the owner(s) of the organization it
 * bills. Callers authorise first.
 */
class SendInvoiceToOwners
{
    /**
     * @return int number of owners the invoice was sent to
     */
    public function handle(Invoice $invoice): int
    {
        $tenant = $invoice->tenant;

        if ($tenant === null) {
            return 0;
        }

        $owners = Tenancy::run($tenant, fn () => User::query()
            ->where('tenant_id', $tenant->getKey())
            ->get()
            ->filter(fn (User $user): bool => $user->hasRole(Role::TenantOwner->value))
            ->values());

        if ($owners->isEmpty()) {
            return 0;
        }

        Notification::send($owners, new InvoiceSentNotification($invoice));

        return $owners->count();
    }
}
