<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Enums\PaymentProvider;
use App\Enums\PlatformPermission;
use App\Livewire\Concerns\BindsTenant;
use App\Livewire\Platform\Concerns\WithPlatformGuard;
use App\Models\PlatformSetting;
use App\Services\Payment\GatewayCredentials;
use App\Services\Payment\PaymentManager;
use App\Support\Auditor;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Operator-wide settings. The payment gateway chosen here is the one every
 * tenant pays through — tenants never pick it themselves. Gateway credentials
 * are edited here too (secrets encrypted, write-only); .env is the fallback.
 */
class Settings extends Component
{
    use BindsTenant;
    use WithPlatformGuard;

    public string $paymentProvider = '';

    /** @var array<string, string> */
    public array $paystack = ['public_key' => '', 'secret_key' => ''];

    /** @var array<string, string> */
    public array $mpesa = ['environment' => 'sandbox', 'consumer_key' => '', 'consumer_secret' => '', 'shortcode' => '', 'passkey' => ''];

    public ?string $notice = null;

    public function mount(PaymentManager $payments, GatewayCredentials $credentials): void
    {
        $this->authorizePlatform(PlatformPermission::SettingsManage);
        $this->paymentProvider = $payments->activeProvider()->value;
        $this->fillCredentialForms($credentials);
    }

    public function save(PaymentManager $payments, Auditor $auditor): void
    {
        $this->authorizePlatform(PlatformPermission::SettingsManage);

        $this->validate([
            'paymentProvider' => ['required', Rule::in($payments->selectableProviders())],
        ]);

        $previous = $payments->activeProvider();

        PlatformSetting::put(PaymentManager::ACTIVE_PROVIDER_SETTING, $this->paymentProvider);

        $auditor->log('platform.settings.payment_provider_changed', null, [
            'from' => $previous->value,
            'to' => $this->paymentProvider,
        ]);

        $this->notice = __('Tenants now pay through :provider.', ['provider' => PaymentProvider::from($this->paymentProvider)->label()]);
    }

    public function saveCredentials(string $provider, GatewayCredentials $credentials, Auditor $auditor): void
    {
        $this->authorizePlatform(PlatformPermission::SettingsManage);

        $gateway = PaymentProvider::from($provider);

        $rules = match ($gateway) {
            PaymentProvider::Paystack => [
                'paystack.public_key' => ['nullable', 'string', 'max:200', 'starts_with:pk_'],
                'paystack.secret_key' => ['nullable', 'string', 'max:200', 'starts_with:sk_'],
            ],
            PaymentProvider::Mpesa => [
                'mpesa.environment' => ['required', Rule::in(['sandbox', 'production'])],
                'mpesa.consumer_key' => ['nullable', 'string', 'max:200'],
                'mpesa.consumer_secret' => ['nullable', 'string', 'max:200'],
                'mpesa.shortcode' => ['nullable', 'string', 'max:20'],
                'mpesa.passkey' => ['nullable', 'string', 'max:200'],
            ],
            default => abort(422),
        };

        $this->validate($rules, [
            'paystack.public_key.starts_with' => __('A Paystack public key starts with pk_test_ or pk_live_.'),
            'paystack.secret_key.starts_with' => __('A Paystack secret key starts with sk_test_ or sk_live_.'),
        ]);

        $credentials->save($gateway, $gateway === PaymentProvider::Paystack ? $this->paystack : $this->mpesa);

        // Record which fields changed, never their values.
        $auditor->log('platform.settings.gateway_credentials_updated', null, [
            'provider' => $gateway->value,
            'fields' => array_keys(array_filter($gateway === PaymentProvider::Paystack ? $this->paystack : $this->mpesa, 'filled')),
        ]);

        $this->fillCredentialForms($credentials);
        $this->notice = __(':provider credentials saved.', ['provider' => $gateway->label()]);
    }

    public function render(PaymentManager $payments, GatewayCredentials $credentials): View
    {
        return view('livewire.platform.settings', [
            'providers' => array_map(PaymentProvider::from(...), $payments->selectableProviders()),
            'configured' => [
                PaymentProvider::Paystack->value => $credentials->isConfigured(PaymentProvider::Paystack),
                PaymentProvider::Mpesa->value => $credentials->isConfigured(PaymentProvider::Mpesa),
            ],
            'secretsSet' => [
                PaymentProvider::Paystack->value => $credentials->formState(PaymentProvider::Paystack)['secrets_set'],
                PaymentProvider::Mpesa->value => $credentials->formState(PaymentProvider::Mpesa)['secrets_set'],
            ],
        ]);
    }

    /**
     * Non-secret values are shown; secret inputs always start blank (write-only).
     */
    private function fillCredentialForms(GatewayCredentials $credentials): void
    {
        $paystack = $credentials->formState(PaymentProvider::Paystack)['values'];
        $mpesa = $credentials->formState(PaymentProvider::Mpesa)['values'];

        $this->paystack = ['public_key' => $paystack['public_key'] ?? '', 'secret_key' => ''];
        $this->mpesa = [
            'environment' => in_array($mpesa['environment'] ?? '', ['sandbox', 'production'], true) ? $mpesa['environment'] : 'sandbox',
            'consumer_key' => '',
            'consumer_secret' => '',
            'shortcode' => $mpesa['shortcode'] ?? '',
            'passkey' => '',
        ];
    }
}
