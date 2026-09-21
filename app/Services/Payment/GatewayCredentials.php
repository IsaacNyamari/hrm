<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\PaymentProvider;
use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Payment gateway credentials, editable by the platform admin (Platform →
 * Settings) and stored in platform_settings. Secret fields are encrypted with
 * the app key. Anything not set in the database falls back to .env / config,
 * so existing deployments keep working.
 */
class GatewayCredentials
{
    /**
     * Editable fields per provider: field => [config key, is secret].
     *
     * @var array<string, array<string, array{0: string, 1: bool}>>
     */
    public const FIELDS = [
        'paystack' => [
            'public_key' => ['paystack.public_key', false],
            'secret_key' => ['paystack.secret_key', true],
        ],
        'mpesa' => [
            'environment' => ['services.mpesa.environment', false],
            'consumer_key' => ['services.mpesa.consumer_key', true],
            'consumer_secret' => ['services.mpesa.consumer_secret', true],
            'shortcode' => ['services.mpesa.shortcode', false],
            'passkey' => ['services.mpesa.passkey', true],
        ],
    ];

    private const BASE_URLS = [
        'sandbox' => 'https://sandbox.safaricom.co.ke',
        'production' => 'https://api.safaricom.co.ke',
    ];

    /**
     * The effective config for a provider: config() overlaid with stored values.
     *
     * @return array<string, mixed>
     */
    public function for(PaymentProvider $provider): array
    {
        $config = match ($provider) {
            PaymentProvider::Paystack => (array) config('paystack', []),
            PaymentProvider::Mpesa => (array) config('services.mpesa', []),
            default => [],
        };

        $stored = $this->stored($provider);

        foreach ($stored as $field => $value) {
            if (filled($value)) {
                $config[$field] = $value;
            }
        }

        // A stored M-Pesa environment also selects the matching Safaricom host.
        $environment = $stored['environment'] ?? null;

        if ($provider === PaymentProvider::Mpesa && is_string($environment) && isset(self::BASE_URLS[$environment])) {
            $config['base_url'] = self::BASE_URLS[$environment];
        }

        return $config;
    }

    public function isConfigured(PaymentProvider $provider): bool
    {
        $config = $this->for($provider);

        return match ($provider) {
            PaymentProvider::Paystack => filled($config['secret_key'] ?? null),
            PaymentProvider::Mpesa => filled($config['consumer_key'] ?? null)
                && filled($config['consumer_secret'] ?? null)
                && filled($config['shortcode'] ?? null)
                && filled($config['passkey'] ?? null),
            default => true,
        };
    }

    /**
     * Non-secret values for the settings form, plus which secrets are set.
     *
     * @return array{values: array<string, string>, secrets_set: array<string, bool>}
     */
    public function formState(PaymentProvider $provider): array
    {
        $effective = $this->for($provider);
        $values = [];
        $secretsSet = [];

        foreach (self::FIELDS[$provider->value] ?? [] as $field => [, $secret]) {
            if ($secret) {
                $secretsSet[$field] = filled($effective[$field] ?? null);
            } else {
                $values[$field] = (string) ($effective[$field] ?? '');
            }
        }

        return ['values' => $values, 'secrets_set' => $secretsSet];
    }

    /**
     * Save submitted fields. Blank secrets are left unchanged.
     *
     * @param  array<string, string|null>  $input
     */
    public function save(PaymentProvider $provider, array $input): void
    {
        $stored = $this->storedRaw($provider);

        foreach (self::FIELDS[$provider->value] ?? [] as $field => [, $secret]) {
            $value = trim((string) ($input[$field] ?? ''));

            if ($secret) {
                if ($value !== '') {
                    $stored[$field] = Crypt::encryptString($value);
                }

                continue;
            }

            $stored[$field] = $value;
        }

        PlatformSetting::put($this->key($provider), $stored);
    }

    /**
     * @return array<string, string|null>
     */
    private function stored(PaymentProvider $provider): array
    {
        $decrypted = [];

        foreach ($this->storedRaw($provider) as $field => $value) {
            $secret = self::FIELDS[$provider->value][$field][1] ?? false;

            try {
                $decrypted[$field] = $secret && is_string($value) && $value !== '' ? Crypt::decryptString($value) : $value;
            } catch (DecryptException) {
                $decrypted[$field] = null; // written under a different APP_KEY
            }
        }

        return $decrypted;
    }

    /**
     * @return array<string, string|null>
     */
    private function storedRaw(PaymentProvider $provider): array
    {
        return (array) PlatformSetting::get($this->key($provider), []);
    }

    private function key(PaymentProvider $provider): string
    {
        return 'payments.'.$provider->value;
    }
}
