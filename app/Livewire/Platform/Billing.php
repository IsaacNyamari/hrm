<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PlatformPermission;
use App\Enums\SubscriptionStatus;
use App\Livewire\Concerns\BindsTenant;
use App\Livewire\Platform\Concerns\WithPlatformGuard;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only, cross-tenant billing browser for platform operators. One
 * component, three tabs (payments / invoices / subscriptions); the tab is a
 * bookmarkable URL parameter so switching never reloads the page.
 */
class Billing extends Component
{
    use BindsTenant;
    use WithPagination;
    use WithPlatformGuard;

    private const TABS = ['payments', 'invoices', 'subscriptions'];

    #[Url(history: true)]
    public string $tab = 'payments';

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $provider = '';

    public function mount(): void
    {
        $this->authorizePlatform(PlatformPermission::PaymentsView);
    }

    public function updatedTab(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'payments';
        }

        $this->reset('search', 'status', 'provider');
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'provider'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'payments';
        $term = trim($this->search);

        $data = match ($tab) {
            'invoices' => $this->invoices($term),
            'subscriptions' => $this->subscriptions($term),
            default => $this->payments($term),
        };

        return view('livewire.platform.billing', [
            'tab' => $tab,
            ...$data,
        ]);
    }

    /**
     * @return array{rows: LengthAwarePaginator<int, Payment>, statuses: array<int, PaymentStatus>, providers: array<int, PaymentProvider>, collectedCents: int}
     */
    private function payments(string $term): array
    {
        $status = $this->status === '' ? null : PaymentStatus::tryFrom($this->status);
        $provider = $this->provider === '' ? null : PaymentProvider::tryFrom($this->provider);

        $base = Payment::query()
            ->when($status instanceof PaymentStatus, fn (Builder $q) => $q->where('status', $status))
            ->when($provider instanceof PaymentProvider, fn (Builder $q) => $q->where('provider', $provider))
            ->when($term !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($term): void {
                $w->where('reference', 'like', "%{$term}%")
                    ->orWhere('provider_reference', 'like', "%{$term}%")
                    ->orWhere('payer', 'like', "%{$term}%")
                    ->orWhereHas('tenant', fn (Builder $t) => $t->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%"));
            }));

        return [
            'rows' => (clone $base)->with(['tenant:id,name,slug', 'invoice:id,number'])->latest('id')->paginate(30),
            'statuses' => PaymentStatus::cases(),
            'providers' => PaymentProvider::cases(),
            'collectedCents' => (int) (clone $base)->where('status', PaymentStatus::Succeeded)->sum('amount_cents'),
        ];
    }

    /**
     * @return array{rows: LengthAwarePaginator<int, Invoice>, statuses: array<int, InvoiceStatus>, providers: array<int, PaymentProvider>, collectedCents: int}
     */
    private function invoices(string $term): array
    {
        $this->authorizePlatform(PlatformPermission::InvoicesManage);

        $status = $this->status === '' ? null : InvoiceStatus::tryFrom($this->status);

        $rows = Invoice::query()
            ->when($status instanceof InvoiceStatus, fn (Builder $q) => $q->where('status', $status))
            ->when($term !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($term): void {
                $w->where('number', 'like', "%{$term}%")
                    ->orWhereHas('tenant', fn (Builder $t) => $t->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%"));
            }))
            ->with(['tenant:id,name,slug', 'subscription:id,plan_id', 'subscription.plan:id,name'])
            ->latest('id')
            ->paginate(30);

        return ['rows' => $rows, 'statuses' => InvoiceStatus::cases(), 'providers' => [], 'collectedCents' => 0];
    }

    /**
     * @return array{rows: LengthAwarePaginator<int, Subscription>, statuses: array<int, SubscriptionStatus>, providers: array<int, PaymentProvider>, collectedCents: int}
     */
    private function subscriptions(string $term): array
    {
        $this->authorizePlatform(PlatformPermission::SubscriptionsManage);

        $status = $this->status === '' ? null : SubscriptionStatus::tryFrom($this->status);

        $rows = Subscription::query()
            ->when($status instanceof SubscriptionStatus, fn (Builder $q) => $q->where('status', $status))
            ->when($term !== '', fn (Builder $q) => $q->whereHas('tenant', fn (Builder $t) => $t->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%")))
            ->with(['tenant:id,name,slug', 'plan:id,name,price_cents,currency,billing_interval'])
            ->latest('id')
            ->paginate(30);

        return ['rows' => $rows, 'statuses' => SubscriptionStatus::cases(), 'providers' => [], 'collectedCents' => 0];
    }
}
