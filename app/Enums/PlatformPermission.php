<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Platform-side (SaaS operator) permission catalogue. Stored as Spatie
 * permissions under the reserved team id 0.
 */
enum PlatformPermission: string
{
    case DashboardView = 'platform.dashboard.view';
    case TenantsView = 'platform.tenants.view';
    case TenantsManage = 'platform.tenants.manage';
    case TenantsImpersonate = 'platform.tenants.impersonate';
    case SubscriptionsManage = 'platform.subscriptions.manage';
    case PlansManage = 'platform.plans.manage';
    case InvoicesManage = 'platform.invoices.manage';
    case PaymentsView = 'platform.payments.view';
    case UsersManage = 'platform.users.manage';
    case SettingsManage = 'platform.settings.manage';
    case AuditView = 'platform.audit.view';
    case LeadsManage = 'platform.leads.manage';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $p): string => $p->value, self::cases());
    }
}
