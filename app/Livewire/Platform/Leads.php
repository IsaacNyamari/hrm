<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Actions\Tenancy\SendRegistrationInvitation;
use App\Enums\LeadStatus;
use App\Enums\PlatformPermission;
use App\Livewire\Concerns\BindsTenant;
use App\Livewire\Platform\Concerns\WithPlatformGuard;
use App\Models\Lead;
use App\Models\RegistrationInvitation;
use App\Support\Auditor;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Website quote requests / contact messages. Sending an access link is the
 * only way a new organization can register.
 */
class Leads extends Component
{
    use BindsTenant;
    use WithPagination;
    use WithPlatformGuard;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    public ?string $notice = null;

    public ?string $viewingUuid = null;

    /** Shown once when outgoing mail only logs, so the link can be sent by hand. */
    public ?string $manualLink = null;

    public function mount(): void
    {
        $this->authorizePlatform(PlatformPermission::LeadsManage);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function view(string $uuid): void
    {
        $this->viewingUuid = $uuid;
    }

    public function closeView(): void
    {
        $this->viewingUuid = null;
    }

    public function sendAccess(string $uuid, SendRegistrationInvitation $send, Auditor $auditor): void
    {
        $this->authorizePlatform(PlatformPermission::LeadsManage);

        $lead = Lead::query()->where('uuid', $uuid)->firstOrFail();
        $send->handle($lead->email, $lead->name, $lead->company, $lead, auth()->user());

        $auditor->log('platform.lead.access_sent', $lead, ['email' => $lead->email]);

        $this->viewingUuid = null;
        $this->notice = __('Access link sent to :email.', ['email' => $lead->email]);
        $this->manualLink = in_array(config('mail.default'), ['log', 'array'], true) ? $send->lastUrl : null;
    }

    public function setStatus(string $uuid, string $status, Auditor $auditor): void
    {
        $this->authorizePlatform(PlatformPermission::LeadsManage);

        $newStatus = LeadStatus::from($status);
        abort_if($newStatus === LeadStatus::Invited, 422); // only via sendAccess()

        $lead = Lead::query()->where('uuid', $uuid)->firstOrFail();
        $lead->update(['status' => $newStatus]);

        $auditor->log('platform.lead.status_changed', $lead, ['to' => $newStatus->value]);

        $this->notice = __(':name marked as :status.', ['name' => $lead->name, 'status' => $newStatus->label()]);
    }

    public function render(): View
    {
        $term = trim($this->search);

        $leads = Lead::query()
            ->withCount('invitations')
            ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term): void {
                $w->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('company', 'like', "%{$term}%");
            }))
            ->when(LeadStatus::tryFrom($this->status) !== null, fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(15);

        $viewing = $this->viewingUuid !== null
            ? Lead::query()->with(['invitations' => fn ($q) => $q->latest()])->where('uuid', $this->viewingUuid)->first()
            : null;

        return view('livewire.platform.leads', [
            'leads' => $leads,
            'viewing' => $viewing,
            'statuses' => LeadStatus::cases(),
            'newCount' => Lead::query()->where('status', LeadStatus::New)->count(),
            'openLinks' => RegistrationInvitation::query()->whereNull('opened_at')->where('expires_at', '>', now())->count(),
        ]);
    }
}
