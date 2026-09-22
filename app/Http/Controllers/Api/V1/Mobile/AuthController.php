<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\Mobile\IssueMobileToken;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Mobile-app sign-in. Mirrors the web (Fortify) rules — invite-only accounts,
 * verified email, TOTP two-factor with recovery codes — but issues a Sanctum
 * token instead of a session.
 */
class AuthController extends Controller
{
    /** Attempts per minute, per email + IP (same as the web login). */
    private const LOGIN_ATTEMPTS = 5;

    /** Two-factor code attempts per minute, per challenge. */
    private const TWO_FACTOR_ATTEMPTS = 5;

    public function __construct(private readonly IssueMobileToken $issuer) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:80'],
        ]);

        $email = Str::lower(trim($data['email']));
        $throttleKey = 'mobile-login:'.Str::transliterate($email).'|'.$request->ip();

        $this->ensureNotThrottled($throttleKey, self::LOGIN_ATTEMPTS, 'email');

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            event(new Failed('sanctum', $user, ['email' => $email]));

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $this->ensureCanUseApp($user);

        RateLimiter::clear($throttleKey);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $challenge = Str::random(64);

            Cache::put($this->challengeKey($challenge), [
                'user_id' => $user->getKey(),
                'device_name' => $data['device_name'],
            ], (int) config('mobile.two_factor_challenge_ttl', 300));

            return response()->json(['data' => ['two_factor' => true, 'challenge_token' => $challenge]]);
        }

        return $this->signIn($user, $data['device_name']);
    }

    public function twoFactor(Request $request, TwoFactorAuthenticationProvider $provider): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string', 'size:64'],
            'code' => ['nullable', 'required_without:recovery_code', 'string', 'max:10'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:64'],
        ]);

        $throttleKey = 'mobile-2fa:'.hash('sha256', $data['challenge_token']);
        $this->ensureNotThrottled($throttleKey, self::TWO_FACTOR_ATTEMPTS, 'code');

        /** @var array{user_id: int, device_name: string}|null $challenge */
        $challenge = Cache::get($this->challengeKey($data['challenge_token']));
        $user = $challenge === null ? null : User::query()->find($challenge['user_id']);

        if ($challenge === null || $user === null || ! $user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages([
                'challenge_token' => __('This sign-in has expired. Please sign in again.'),
            ]);
        }

        $usingCode = ! empty($data['code']);
        $valid = false;

        if ($usingCode) {
            $valid = $provider->verify(
                Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret),
                preg_replace('/\s+/', '', (string) $data['code']) ?? '',
            );
        } else {
            $recovery = collect($user->recoveryCodes())
                ->first(fn (string $code): bool => hash_equals($code, trim((string) $data['recovery_code'])));

            if ($recovery !== null) {
                $user->replaceRecoveryCode($recovery);
                $valid = true;
            }
        }

        if (! $valid) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages($usingCode
                ? ['code' => __('The provided two factor authentication code was invalid.')]
                : ['recovery_code' => __('The provided two factor recovery code was invalid.')]);
        }

        Cache::forget($this->challengeKey($data['challenge_token']));
        RateLimiter::clear($throttleKey);

        return $this->signIn($user, $challenge['device_name']);
    }

    /**
     * Revoke the token this request was made with, and stop pushes to its device.
     */
    public function logout(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            DeviceToken::query()->where('personal_access_token_id', $token->getKey())->delete();
            $token->delete();
        }

        return response()->noContent();
    }

    /**
     * Always answers the same way, so it cannot be used to discover accounts.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $throttleKey = 'mobile-forgot:'.$request->ip();
        $this->ensureNotThrottled($throttleKey, 5, 'email');
        RateLimiter::hit($throttleKey, 60);

        Password::broker(config('fortify.passwords'))->sendResetLink(['email' => Str::lower(trim($data['email']))]);

        return response()->json(['data' => [
            'message' => __('If that email belongs to an account, we have sent a link to reset the password.'),
        ]]);
    }

    private function signIn(User $user, string $deviceName): JsonResponse
    {
        event(new Login('sanctum', $user, false));

        return response()->json(['data' => $this->issuer->handle($user, $deviceName)]);
    }

    /**
     * The app is for organisation staff. Platform operators, accounts without an
     * organisation and unverified accounts are refused (as on the web).
     */
    private function ensureCanUseApp(User $user): void
    {
        abort_if($user->isPlatformAdmin(), 403, __('Platform administrators sign in on the web.'));
        abort_if($user->tenant_id === null || $user->tenant()->doesntExist(), 403, __('This account is not attached to an organisation.'));
        abort_if(! $user->hasVerifiedEmail(), 403, __('Verify your email address using the link we sent you, then sign in again.'));
    }

    private function ensureNotThrottled(string $key, int $maxAttempts, string $field): void
    {
        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            $field => __('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]),
        ])->status(429);
    }

    private function challengeKey(string $challenge): string
    {
        return 'mobile-2fa-challenge:'.hash('sha256', $challenge);
    }
}
