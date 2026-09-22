<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\TrackedApplicationResource;
use App\Livewire\Marketing\ApplicationTracker;
use App\Models\Application;
use App\Services\Recruitment\RecruitmentService;
use App\Support\Recruitment\ApplicationProgress;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * No-account application tracking: the tracking code *and* the email the
 * candidate applied with must both match. Candidates can accept or decline an
 * open offer. Mirrors the website's {@see ApplicationTracker}.
 */
class ApplicationTrackingController extends Controller
{
    public function track(Request $request): JsonResponse
    {
        $application = $this->lookup($request);

        return response()->json(['data' => (new TrackedApplicationResource($application))->resolve($request)]);
    }

    public function respond(Request $request, RecruitmentService $service, TenantContext $context): JsonResponse
    {
        $accept = $request->validate(['accept' => ['required', 'boolean']])['accept'];
        $application = $this->lookup($request);

        $offer = ApplicationProgress::for($application)->pendingOffer;

        if ($offer === null) {
            throw ValidationException::withMessages(['accept' => __('There is no open offer to respond to.')]);
        }

        $context->run($application->tenant, fn () => $service->respondToOffer($offer->setRelation('application', $application), (bool) $accept));

        $fresh = $this->find((string) $application->tracking_code, (string) $request->input('email'));

        return response()->json([
            'data' => (new TrackedApplicationResource($fresh ?? $application))->resolve($request),
            'meta' => ['message' => $accept
                ? __('Offer accepted — congratulations! HR will now set up your employee account.')
                : __('You have declined the offer. Thank you for letting us know.')],
        ]);
    }

    private function lookup(Request $request): Application
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:150'],
        ], [], ['code' => __('tracking code')]);

        // Shares the website's per-connection budget.
        $key = 'track-application:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages([
                'code' => __('Too many attempts. Please wait a minute and try again.'),
            ])->status(429);
        }

        RateLimiter::hit($key, 60);

        $application = $this->find($data['code'], $data['email']);

        if ($application === null) {
            throw ValidationException::withMessages([
                'code' => __('We could not find an application with that code and email. Check both and try again.'),
            ]);
        }

        return $application;
    }

    private function find(string $code, string $email): ?Application
    {
        return Application::findForTracking($code, $email)?->load([
            'vacancy' => fn ($q) => $q->withoutGlobalScopes(),
            'tenant',
            'applicant' => fn ($q) => $q->withoutGlobalScopes(),
        ]);
    }
}
