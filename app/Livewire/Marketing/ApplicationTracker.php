<?php

declare(strict_types=1);

namespace App\Livewire\Marketing;

use App\Exceptions\RecruitmentException;
use App\Models\Application;
use App\Services\Recruitment\RecruitmentService;
use App\Support\Recruitment\ApplicationProgress;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public "Track application" page. A candidate enters their tracking code and
 * the email they applied with; both must match. They see a plain-language
 * status and can accept or decline a job offer.
 */
class ApplicationTracker extends Component
{
    #[Url(as: 'code', except: '')]
    public string $code = '';

    public string $email = '';

    public bool $found = false;

    public ?string $notice = null;

    /** When the page last received a live update, for the "updated" indicator. */
    public ?string $liveUpdatedAt = null;

    public function lookup(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:150'],
        ], [], ['code' => __('tracking code')]);

        $key = 'track-application:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('code', __('Too many attempts. Please wait a minute and try again.'));

            return;
        }

        RateLimiter::hit($key, 60);

        $this->code = strtoupper(trim($this->code));
        $this->notice = null;
        $this->found = $this->application() !== null;

        if (! $this->found) {
            $this->addError('code', __('We could not find an application with that code and email. Check both and try again.'));
        }
    }

    public function respond(bool $accept, RecruitmentService $service, TenantContext $context): void
    {
        $application = $this->application();
        $offer = $application !== null ? ApplicationProgress::for($application)->pendingOffer : null;

        if ($offer === null) {
            $this->notice = __('There is no open offer to respond to.');

            return;
        }

        try {
            $context->run($application->tenant, fn () => $service->respondToOffer($offer->setRelation('application', $application), $accept));
        } catch (RecruitmentException $e) {
            $this->notice = $e->getMessage();

            return;
        }

        $this->notice = $accept
            ? __('Offer accepted — congratulations! HR will now set up your employee account.')
            : __('You have declined the offer. Thank you for letting us know.');
    }

    /**
     * Called by the page when the application's broadcast channel reports a
     * change; re-rendering re-reads the application with the code and email.
     */
    public function progressUpdated(): void
    {
        $this->liveUpdatedAt = now()->format('H:i:s');
    }

    public function startOver(): void
    {
        $this->reset('code', 'email', 'found', 'notice', 'liveUpdatedAt');
    }

    public function render(): View
    {
        $application = $this->found ? $this->application() : null;

        return view('livewire.marketing.application-tracker', [
            'application' => $application,
            'progress' => $application !== null ? ApplicationProgress::for($application) : null,
        ]);
    }

    private function application(): ?Application
    {
        if ($this->code === '' || $this->email === '') {
            return null;
        }

        $application = Application::findForTracking($this->code, $this->email);

        return $application?->load([
            'vacancy' => fn ($q) => $q->withoutGlobalScopes(),
            'tenant',
            'applicant' => fn ($q) => $q->withoutGlobalScopes(),
        ]);
    }
}
