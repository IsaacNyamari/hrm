<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Enums\BillingInterval;
use App\Enums\PlatformPermission;
use App\Livewire\Concerns\BindsTenant;
use App\Livewire\Platform\Concerns\WithPlatformGuard;
use App\Models\Plan;
use App\Support\Auditor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Plans extends Component
{
    use BindsTenant;
    use WithPlatformGuard;

    #[Locked]
    public ?int $editingId = null;

    public bool $showForm = false;

    public ?string $notice = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorizePlatform(PlatformPermission::PlansManage);
        $this->form = $this->blankForm();
    }

    public function create(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->form = $this->blankForm();
        $this->showForm = true;
    }

    public function edit(int $planId): void
    {
        $this->resetErrorBag();

        $plan = Plan::query()->findOrFail($planId);
        $this->editingId = $plan->getKey();
        $this->form = [
            'name' => $plan->name,
            'description' => $plan->description,
            'price_cents' => $plan->price_cents,
            'billing_interval' => $plan->billing_interval->value,
            'employee_limit' => $plan->employee_limit,
            'storage_limit_mb' => $plan->storage_limit_mb,
            'trial_days' => $plan->trial_days,
            'sort_order' => $plan->sort_order,
            'is_active' => $plan->is_active,
            'flags' => array_values($plan->features['flags'] ?? []),
        ];
        $this->showForm = true;
    }

    public function save(Auditor $auditor): void
    {
        $this->authorizePlatform(PlatformPermission::PlansManage);

        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:120'],
            'form.description' => ['nullable', 'string', 'max:500'],
            'form.price_cents' => ['required', 'integer', 'min:0'],
            'form.billing_interval' => ['required', new Enum(BillingInterval::class)],
            'form.employee_limit' => ['nullable', 'integer', 'min:1'],
            'form.storage_limit_mb' => ['nullable', 'integer', 'min:1'],
            'form.trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'form.sort_order' => ['required', 'integer', 'min:0'],
            'form.is_active' => ['boolean'],
            'form.flags' => ['array'],
            'form.flags.*' => ['string', Rule::in((array) config('billing.feature_flags'))],
        ])['form'];

        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_cents' => (int) $data['price_cents'],
            'billing_interval' => $data['billing_interval'],
            'employee_limit' => $data['employee_limit'] !== null ? (int) $data['employee_limit'] : null,
            'storage_limit_mb' => $data['storage_limit_mb'] !== null ? (int) $data['storage_limit_mb'] : null,
            'trial_days' => (int) $data['trial_days'],
            'sort_order' => (int) $data['sort_order'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];

        $flags = array_values(array_unique($data['flags'] ?? []));

        if ($this->editingId !== null) {
            $plan = Plan::query()->findOrFail($this->editingId);
            $attributes['features'] = ['flags' => $flags, 'limits' => $plan->features['limits'] ?? []];
            $plan->update($attributes);
            $auditor->log('platform.plan.updated', $plan, ['slug' => $plan->slug]);
            $this->notice = __('Plan “:name” updated.', ['name' => $plan->name]);
        } else {
            $attributes['currency'] = 'KES';
            $attributes['features'] = ['flags' => $flags, 'limits' => []];
            $attributes['slug'] = Str::slug((string) $attributes['name']).'-'.Str::lower(Str::random(4));
            $plan = Plan::query()->create($attributes);
            $auditor->log('platform.plan.created', $plan, ['slug' => $plan->slug]);
            $this->notice = __('Plan “:name” created.', ['name' => $plan->name]);
        }

        $this->showForm = false;
        $this->editingId = null;
        $this->form = $this->blankForm();
    }

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        return [
            'name' => '',
            'description' => '',
            'price_cents' => 0,
            'billing_interval' => BillingInterval::Monthly->value,
            'employee_limit' => null,
            'storage_limit_mb' => null,
            'trial_days' => 14,
            'sort_order' => 0,
            'is_active' => true,
            'flags' => [],
        ];
    }

    public function render(): View
    {
        return view('livewire.platform.plans', [
            'plans' => Plan::query()->withCount('subscriptions')->orderBy('sort_order')->orderBy('price_cents')->get(),
            'intervals' => BillingInterval::cases(),
        ]);
    }

    public function delete(int $planId, Auditor $auditor): void
    {
        $this->authorizePlatform(PlatformPermission::PlansManage);

        $plan = Plan::query()->findOrFail($planId);
        $plan->delete();
        $auditor->log('platform.plan.deleted', $plan, ['slug' => $plan->slug]);
        $this->notice = __('Plan “:name” deleted.', ['name' => $plan->name]);
    }
}
