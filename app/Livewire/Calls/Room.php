<?php

declare(strict_types=1);

namespace App\Livewire\Calls;

use App\Exceptions\CallException;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Call;
use App\Services\Calls\CallService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Almost the entire interactive surface here (the online-colleagues list, the
 * video grid, WebRTC itself) is owned by client-side JS reacting to a Reverb
 * presence channel — see resources/js/calls.js. This component is a thin
 * action layer: it persists call state to the database and tells that JS,
 * via dispatched browser events, when something server-side has changed.
 */
class Room extends Component
{
    use BindsTenant;

    #[Url]
    public string $call = '';

    /**
     * @param  array<int, int|string>  $userIds
     */
    public function startCall(array $userIds, CallService $service): void
    {
        try {
            $newCall = $service->start(auth()->user(), array_map('intval', $userIds));
            $this->call = $newCall->uuid;
            $this->dispatch('call-started', uuid: $newCall->uuid);
        } catch (CallException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function acceptCall(string $uuid, CallService $service): void
    {
        $call = Call::query()->where('uuid', $uuid)->first();

        if ($call === null) {
            return;
        }

        try {
            $service->accept($call, auth()->user());
            $this->call = $uuid;
            $this->dispatch('call-started', uuid: $uuid);
        } catch (CallException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function declineCall(string $uuid, CallService $service): void
    {
        $call = Call::query()->where('uuid', $uuid)->first();

        if ($call !== null) {
            $service->decline($call, auth()->user());
        }
    }

    public function leaveCall(CallService $service): void
    {
        if ($this->call === '') {
            return;
        }

        $call = Call::query()->where('uuid', $this->call)->first();

        if ($call !== null) {
            $service->leave($call, auth()->user());
        }

        $this->call = '';
    }

    public function endCallForEveryone(CallService $service): void
    {
        if ($this->call === '') {
            return;
        }

        $call = Call::query()->where('uuid', $this->call)->first();

        if ($call !== null && $call->created_by === auth()->id()) {
            $service->endForEveryone($call);
        }

        $this->call = '';
    }

    /**
     * @param  array<int, int|string>  $userIds
     */
    public function inviteToCall(array $userIds, CallService $service): void
    {
        if ($this->call === '') {
            return;
        }

        $call = Call::query()->where('uuid', $this->call)->first();

        if ($call === null) {
            return;
        }

        try {
            $service->invite($call, auth()->user(), array_map('intval', $userIds));
        } catch (CallException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.calls.room', [
            'authUserId' => $user->id,
            'authUserName' => $user->name,
            'authUserInitials' => $user->initials(),
            'tenantId' => $user->tenant_id,
            'initialCallUuid' => $this->call,
        ]);
    }
}
