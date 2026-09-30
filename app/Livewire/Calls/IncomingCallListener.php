<?php

declare(strict_types=1);

namespace App\Livewire\Calls;

use App\Exceptions\CallException;
use App\Models\Call;
use App\Services\Calls\CallService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Mounted once in the main layout for every tenant user, regardless of what
 * page they're on, so a call can actually reach them.
 */
class IncomingCallListener extends Component
{
    public function accept(string $uuid, CallService $service): void
    {
        $call = Call::query()->where('uuid', $uuid)->first();

        if ($call === null) {
            return;
        }

        try {
            $service->accept($call, auth()->user());
            $this->redirect(route('meetings', ['call' => $uuid]), navigate: true);
        } catch (CallException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function decline(string $uuid, CallService $service): void
    {
        $call = Call::query()->where('uuid', $uuid)->first();

        if ($call !== null) {
            $service->decline($call, auth()->user());
        }
    }

    public function render(): View
    {
        return view('livewire.calls.incoming-call-listener', [
            'authUserId' => auth()->id(),
        ]);
    }
}
