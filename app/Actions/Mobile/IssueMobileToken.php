<?php

declare(strict_types=1);

namespace App\Actions\Mobile;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Signs a user in on the mobile app: issues a Sanctum token named after the
 * device, carrying only the `mobile` ability (so it can never reach the
 * integration endpoints), and returns what the app needs to start up.
 */
class IssueMobileToken
{
    /** The single ability every mobile-app token carries. */
    public const ABILITY = 'mobile';

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @return array{token: string, user: array<string, mixed>, employee: array<string, mixed>|null, abilities: array<int, string>, roles: array<int, string>, self_service_only: bool, manages_people: bool, tenant: array<string, mixed>}
     */
    public function handle(User $user, string $deviceName): array
    {
        $token = $user->createToken($deviceName, [self::ABILITY]);

        return ['token' => $token->plainTextToken] + $this->profile($user);
    }

    /**
     * The signed-in user, their employee record, what they may do (Spatie
     * permission names, evaluated in their organisation) and the organisation's
     * name, currency and branding.
     *
     * @return array{user: array<string, mixed>, employee: array<string, mixed>|null, abilities: array<int, string>, roles: array<int, string>, self_service_only: bool, manages_people: bool, tenant: array<string, mixed>}
     */
    public function profile(User $user): array
    {
        /** @var Tenant $tenant */
        $tenant = $user->tenant()->firstOrFail();
        $tenant->loadMissing('branding');

        return $this->context->run($tenant, function () use ($user, $tenant): array {
            $employee = $user->employee()
                ->with(['department:id,name', 'branch:id,name', 'manager:id,first_name,middle_name,last_name'])
                ->first();

            return [
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => $user->initials(),
                    'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
                ],
                'employee' => $employee === null ? null : [
                    'uuid' => $employee->uuid,
                    'number' => $employee->employee_number,
                    'name' => $employee->displayName(),
                    'job_title' => $employee->job_title,
                    'department' => $employee->department?->name,
                    'branch' => $employee->branch?->name,
                    'manager' => $employee->manager?->fullName(),
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                    'hire_date' => $employee->hire_date?->toDateString(),
                    'status' => $employee->status->label(),
                ],
                'abilities' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
                'roles' => $user->getRoleNames()->values()->all(),
                'self_service_only' => $user->isSelfServiceOnly(),
                // Line managers approve their reports' leave even without the leave.approve permission.
                'manages_people' => $employee !== null && $employee->directReports()->exists(),
                'tenant' => [
                    'uuid' => $tenant->uuid,
                    'name' => $tenant->name,
                    'currency' => $tenant->currency,
                    'timezone' => $tenant->timezone,
                    'branding' => [
                        'name' => $tenant->branding?->brand_name ?: $tenant->name,
                        'primary_color' => $tenant->branding?->primary_color,
                        'accent_color' => $tenant->branding?->accent_color,
                        'logo_url' => $tenant->branding?->logoUrl(),
                        'support_email' => $tenant->branding?->support_email,
                    ],
                ],
            ];
        });
    }
}
