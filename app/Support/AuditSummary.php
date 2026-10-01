<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Turns an audit entry's raw properties into short sentences a person can
 * read, instead of showing the stored JSON.
 */
class AuditSummary
{
    /**
     * @var array<string, string>
     */
    private const TITLES = [
        'auth.login' => 'Signed in',
        'auth.logout' => 'Signed out',
        'auth.failed' => 'Failed sign-in',
        'auth.lockout' => 'Account locked after too many attempts',
        'impersonation.started' => 'Started impersonating a user',
        'impersonation.stopped' => 'Stopped impersonating a user',
        'platform.tenant.activated' => 'Organization activated',
        'platform.tenant.suspended' => 'Organization suspended',
        'platform.tenant.user_role_changed' => 'User role changed by platform operator',
        'platform.plan.created' => 'Plan created',
        'platform.plan.updated' => 'Plan updated',
        'platform.plan.deleted' => 'Plan deleted',
        'platform.invoice.sent' => 'Invoice emailed to owner',
        'platform.invoice.deleted' => 'Invoice deleted',
        'platform.lead.access_sent' => 'Registration link sent to lead',
        'platform.lead.status_changed' => 'Lead status changed',
        'platform.settings.payment_provider_changed' => 'Payment provider changed',
        'platform.settings.gateway_credentials_updated' => 'Payment gateway credentials updated',
        'platform.deployment.pull_started' => 'Deployment started',
        'platform.deployment.rollback_started' => 'Rollback started',
        'platform.audit.cleared' => 'Platform audit trail cleared',
        'platform.audit.entry_deleted' => 'Platform audit entry deleted',
        'audit.cleared' => 'Audit trail cleared',
        'audit.entry_deleted' => 'Audit entry deleted',
        'audit.exported' => 'Audit trail exported',
        'report.exported' => 'Report exported',
        'employee.data_exported' => 'Employee data exported',
        'leave.approved' => 'Leave approved',
        'leave.rejected' => 'Leave rejected',
        'expense.approved' => 'Expense claim approved',
        'expense.rejected' => 'Expense claim rejected',
        'tenant.user.role_changed' => 'User role changed',
        'tenant.role_deleted' => 'Custom role deleted',
        'tenant.role_updated' => 'Custom role updated',
        'attendance.manual_added' => 'Attendance record added manually',
        'attendance.manual_edited' => 'Attendance record edited manually',
        'attendance.manual_deleted' => 'Attendance record deleted',
        'attendance.correction_requested' => 'Attendance correction requested',
        'attendance.correction_approved' => 'Attendance correction approved',
        'attendance.correction_rejected' => 'Attendance correction rejected',
        'attendance.approval_setting_changed' => 'Attendance approval setting changed',
        'payroll.pay_rules_updated' => 'Pay rules updated',
        'payroll.final_settlement_prepared' => 'Final settlement prepared',
        'payroll.statutory_return_exported' => 'Statutory return exported',
    ];

    /**
     * A readable name for an event key such as `platform.tenant.activated`.
     */
    public function title(string $event): string
    {
        if (isset(self::TITLES[$event])) {
            return self::TITLES[$event];
        }

        $parts = explode('.', Str::after($event, 'platform.'));
        $verb = array_pop($parts);
        $subject = str_replace('_', ' ', implode(' ', $parts));

        return ucfirst(trim($subject.' '.str_replace('_', ' ', $verb)));
    }

    /**
     * @return array<int, string>
     */
    public function lines(AuditLog $log): array
    {
        /** @var array<string, mixed> $p */
        $p = $log->properties ?? [];

        if ($p === []) {
            return [];
        }

        $lines = $this->known($log->event, $p) ?? $this->generic($log->event, $p);

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<int, string>|null
     */
    private function known(string $event, array $p): ?array
    {
        return match (true) {
            in_array($event, ['platform.tenant.activated', 'platform.tenant.suspended'], true) => [
                sprintf('%s was changed from %s to %s.', $this->tenantName($p['tenant_id'] ?? null), $this->value($p['from'] ?? null), $this->value($p['to'] ?? null)),
            ],
            $event === 'platform.tenant.user_role_changed', $event === 'tenant.user.role_changed' => [
                sprintf('Role changed from %s to %s.', $this->value($p['from'] ?? null), $this->value($p['to'] ?? null)),
            ],
            $event === 'platform.settings.payment_provider_changed' => [
                sprintf('Payment provider changed from %s to %s.', $this->value($p['from'] ?? null), $this->value($p['to'] ?? null)),
            ],
            $event === 'platform.settings.gateway_credentials_updated' => [
                sprintf('%s credentials updated (%s). The values themselves are never recorded.', Str::headline((string) ($p['provider'] ?? 'Gateway')), $this->value($p['fields'] ?? [])),
            ],
            $event === 'impersonation.started' => [
                sprintf('%s signed in as %s.', $this->value($p['operator'] ?? null), $this->value($p['target'] ?? null)),
            ],
            $event === 'impersonation.stopped' => ['The operator stopped impersonating this user.'],
            $event === 'platform.lead.access_sent' => [sprintf('Registration link emailed to %s.', $this->value($p['email'] ?? null))],
            $event === 'platform.lead.status_changed' => [sprintf('Lead status set to %s.', $this->value($p['to'] ?? null))],
            in_array($event, ['platform.plan.created', 'platform.plan.updated', 'platform.plan.deleted'], true) => [
                sprintf('Plan "%s" was %s.', $this->value($p['slug'] ?? null), Str::afterLast($event, '.')),
            ],
            in_array($event, ['platform.invoice.sent', 'platform.invoice.deleted'], true) => [
                sprintf('Invoice %s was %s.', $this->value($p['number'] ?? null), Str::afterLast($event, '.')),
            ],
            in_array($event, ['audit.cleared', 'platform.audit.cleared'], true) => [sprintf('%s audit entries were deleted.', $this->value($p['entries'] ?? 0))],
            $event === 'auth.failed' => [sprintf('Failed sign-in attempt for %s.', $this->value($p['email'] ?? null))],
            $event === 'auth.lockout' => [sprintf('Too many sign-in attempts from %s.', $this->value($p['ip'] ?? null))],
            $event === 'leave.approved' => [sprintf('Leave approved for %s (%s days).', $this->value($p['employee'] ?? null), $this->value($p['days'] ?? null))],
            $event === 'leave.rejected' => [sprintf('Leave rejected for %s.', $this->value($p['employee'] ?? null))],
            in_array($event, ['expense.approved', 'expense.rejected'], true) => [
                sprintf('Claim %s (%s) was %s.', $this->value($p['reference'] ?? null), $this->money($p['total_cents'] ?? 0), Str::afterLast($event, '.')),
            ],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<int, string>
     */
    private function generic(string $event, array $p): array
    {
        $verb = Str::afterLast($event, '.');

        if ($verb === 'updated' && isset($p['changes']) && is_array($p['changes'])) {
            $original = is_array($p['original'] ?? null) ? $p['original'] : [];
            $lines = [];

            foreach ($p['changes'] as $key => $new) {
                $label = $this->label((string) $key);
                $lines[] = array_key_exists($key, $original)
                    ? sprintf('%s changed from %s to %s.', $label, $this->field((string) $key, $original[$key]), $this->field((string) $key, $new))
                    : sprintf('%s set to %s.', $label, $this->field((string) $key, $new));
            }

            return $lines;
        }

        if ($verb === 'created' && isset($p['attributes']) && is_array($p['attributes'])) {
            $skip = ['id', 'uuid', 'tenant_id', 'created_at', 'updated_at'];
            $parts = [];

            foreach ($p['attributes'] as $key => $value) {
                if (in_array($key, $skip, true) || $value === null || $value === '') {
                    continue;
                }
                $parts[] = $this->label((string) $key).': '.$this->field((string) $key, $value);
            }

            return $parts === [] ? ['Created.'] : ['Created with '.implode(', ', $parts).'.'];
        }

        $lines = [];

        foreach ($p as $key => $value) {
            if ($value === null || $value === [] || $value === '') {
                continue;
            }
            $lines[] = $this->label((string) $key).': '.$this->field((string) $key, $value);
        }

        return $lines;
    }

    private function tenantName(mixed $id): string
    {
        $tenant = $id === null ? null : Tenant::query()->find($id);

        return $tenant?->name !== null ? '"'.$tenant->name.'"' : 'Organization #'.$this->value($id);
    }

    private function label(string $key): string
    {
        return Str::headline(Str::replaceLast('_cents', '', $key));
    }

    private function field(string $key, mixed $value): string
    {
        return str_ends_with($key, '_cents') && is_numeric($value) ? $this->money($value) : $this->value($value);
    }

    private function money(mixed $cents): string
    {
        return number_format(((int) $cents) / 100, 2);
    }

    private function value(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => 'empty',
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) => $value === [] ? 'none' : implode(', ', array_map(fn (mixed $v): string => is_array($v) ? json_encode($v, JSON_UNESCAPED_SLASHES) ?: '' : $this->value($v), $value)),
            default => Str::limit((string) $value, 120),
        };
    }
}
