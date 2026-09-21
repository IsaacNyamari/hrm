<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Enums\PaymentProvider;
use App\Enums\PlatformPermission;
use App\Livewire\Concerns\BindsTenant;
use App\Livewire\Platform\Concerns\WithPlatformGuard;
use App\Models\PlatformSetting;
use App\Services\Payment\PaymentManager;
use App\Support\Auditor;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Operator-wide settings. The payment gateway chosen here is the one every
 * tenant pays through — tenants never pick it themselves.
 */
class Settings extends Component
{
    use BindsTenant;
    use WithPlatformGuard;

    public string $paymentProvider = '';

    public ?string $notice = null;

    public function mount(PaymentManager $payments): void
    {
        $this->authorizePlatform(PlatformPermission::SettingsManage);
        $this->paymentProvider = $payments->activeProvider()->value;
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

    public function render(PaymentManager $payments): View
    {
        return view('livewire.platform.settings', [
            'providers' => array_map(PaymentProvider::from(...), $payments->selectableProviders()),
            'configured' => [
                PaymentProvider::Paystack->value => filled(config('paystack.secret_key')),
                PaymentProvider::Mpesa->value => filled(config('services.mpesa.consumer_key')) && filled(config('services.mpesa.consumer_secret')),
            ],
        ]);
    }
}
