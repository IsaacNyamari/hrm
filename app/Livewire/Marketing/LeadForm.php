<?php

declare(strict_types=1);

namespace App\Livewire\Marketing;

use App\Enums\LeadKind;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\NewLeadNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Public "request a quote" / "contact us" form. Stores a Lead and notifies the
 * platform admins. Guarded by a honeypot field and a per-IP rate limit.
 */
class LeadForm extends Component
{
    #[Locked]
    public string $kind = 'quote';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $company = '';

    public ?int $employee_count = null;

    public string $plan_interest = '';

    public string $message = '';

    /** Honeypot: humans never see or fill this. */
    public string $website = '';

    public bool $sent = false;

    public function mount(string $kind = 'quote', ?string $plan = null): void
    {
        $this->kind = LeadKind::tryFrom($kind)?->value ?? LeadKind::Quote->value;
        $this->plan_interest = (string) $plan;
    }

    public function submit(): void
    {
        $isQuote = $this->kind === LeadKind::Quote->value;

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => [$isQuote ? 'required' : 'nullable', 'string', 'max:150'],
            'employee_count' => [$isQuote ? 'required' : 'nullable', 'integer', 'min:1', 'max:100000'],
            'plan_interest' => ['nullable', 'string', 'max:60'],
            'message' => [$isQuote ? 'nullable' : 'required', 'string', 'max:3000'],
            'kind' => ['required', Rule::enum(LeadKind::class)],
        ]);

        if ($this->website !== '') {
            $this->sent = true; // silently drop bots

            return;
        }

        $key = 'lead-form:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', __('Too many submissions. Please try again in a few minutes.'));

            return;
        }

        RateLimiter::hit($key, 600);

        $lead = Lead::query()->create([
            'kind' => $this->kind,
            'status' => LeadStatus::New,
            'name' => trim($this->name),
            'email' => strtolower(trim($this->email)),
            'phone' => $this->phone ?: null,
            'company' => $this->company ?: null,
            'employee_count' => $this->employee_count,
            'plan_interest' => $this->plan_interest ?: null,
            'message' => $this->message ?: null,
            'ip_address' => request()->ip(),
        ]);

        Notification::send(User::query()->where('is_platform_admin', true)->get(), new NewLeadNotification($lead));

        $this->reset('name', 'email', 'phone', 'company', 'employee_count', 'message');
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.marketing.lead-form', [
            'plans' => $this->kind === LeadKind::Quote->value
                ? Plan::query()->where('is_active', true)->orderBy('sort_order')->get(['slug', 'name'])
                : collect(),
        ]);
    }
}
