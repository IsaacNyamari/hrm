<?php

declare(strict_types=1);

namespace App\Actions\Employees;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\WelcomeNewHireNotification;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

/**
 * Gives an employee a login: creates a self-service (Employee role) user in
 * their organization, links it to the employee record and emails a one-time
 * link to choose a password. Until then they can sign in with their email as
 * the password, and are made to change it straight away.
 */
class CreateStaffAccount
{
    public const LINK_DAYS = 7;

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @return User|null the account, or null when it cannot be created (no email,
     *                   or the address already belongs to another organization)
     */
    public function handle(Employee $employee): ?User
    {
        if ($employee->user_id !== null) {
            return $employee->user()->first();
        }

        $email = mb_strtolower(trim((string) $employee->email));

        if ($email === '') {
            return null;
        }

        $tenant = $employee->tenant()->firstOrFail();

        return $this->context->run($tenant, function () use ($employee, $email, $tenant): ?User {
            $user = User::query()->where('email', $email)->first();

            if ($user !== null && ($user->tenant_id !== $tenant->getKey() || $user->employee()->exists())) {
                return null;
            }

            if ($user === null) {
                $user = new User(['name' => $employee->fullName(), 'email' => $email, 'password' => Hash::make($email)]);
                $user->forceFill([
                    'tenant_id' => $tenant->getKey(),
                    'email_verified_at' => CarbonImmutable::now(),
                    'must_change_password' => true,
                ])->save();
            }

            if (! $user->hasRole(Role::Employee->value)) {
                $user->assignRole(Role::Employee->value);
            }

            $employee->forceFill(['user_id' => $user->getKey()])->save();

            $user->notify(new WelcomeNewHireNotification($tenant, self::welcomeUrl($user)));

            return $user;
        });
    }

    /**
     * A signed link to set the first password. It carries a fingerprint of the
     * current password hash, so it stops working as soon as a password is set.
     */
    public static function welcomeUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'welcome.show',
            CarbonImmutable::now()->addDays(self::LINK_DAYS),
            ['user' => $user->uuid, 'fp' => self::fingerprint($user)],
        );
    }

    public static function fingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', (string) $user->getAuthPassword(), (string) config('app.key')), 0, 20);
    }
}
