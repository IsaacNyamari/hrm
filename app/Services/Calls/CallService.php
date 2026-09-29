<?php

declare(strict_types=1);

namespace App\Services\Calls;

use App\Enums\CallParticipantStatus;
use App\Enums\CallStatus;
use App\Events\CallDeclined;
use App\Events\CallEnded;
use App\Events\IncomingCall;
use App\Exceptions\CallException;
use App\Models\Call;
use App\Models\CallParticipant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CallService
{
    /**
     * Start a call: the initiator is joined immediately, every invitee is
     * rung on their own notification channel.
     *
     * @param  array<int, int>  $inviteeUserIds
     */
    public function start(User $initiator, array $inviteeUserIds): Call
    {
        $inviteeIds = collect($inviteeUserIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->reject(fn (int $id): bool => $id === $initiator->id)
            ->values();

        if ($inviteeIds->isEmpty()) {
            throw CallException::noInvitees();
        }

        $invitees = User::query()
            ->where('tenant_id', $initiator->tenant_id)
            ->whereIn('id', $inviteeIds)
            ->get();

        if ($invitees->isEmpty()) {
            throw CallException::noInvitees();
        }

        $call = Call::query()->create([
            'created_by' => $initiator->id,
            'status' => CallStatus::Ringing,
        ]);

        CallParticipant::query()->create([
            'call_id' => $call->id,
            'user_id' => $initiator->id,
            'status' => CallParticipantStatus::Joined,
            'invited_at' => CarbonImmutable::now(),
            'joined_at' => CarbonImmutable::now(),
        ]);

        foreach ($invitees as $invitee) {
            CallParticipant::query()->create([
                'call_id' => $call->id,
                'user_id' => $invitee->id,
                'status' => CallParticipantStatus::Invited,
                'invited_at' => CarbonImmutable::now(),
            ]);

            IncomingCall::dispatch($invitee->id, $call->uuid, $initiator->name, $initiator->initials(), 1 + $invitees->count());
        }

        return $call;
    }

    /**
     * Invite more people to a call that's already under way.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, User>
     */
    public function invite(Call $call, User $inviter, array $userIds): Collection
    {
        $this->guardSameTenant($call, $inviter);

        if (! $call->status->isOpen()) {
            throw CallException::alreadyEnded();
        }

        $existingIds = $call->participants()->pluck('user_id');

        $newIds = collect($userIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->reject(fn (int $id): bool => $id === $inviter->id || $existingIds->contains($id))
            ->values();

        $newInvitees = User::query()
            ->where('tenant_id', $inviter->tenant_id)
            ->whereIn('id', $newIds)
            ->get();

        $totalParticipants = $existingIds->count() + $newInvitees->count();

        foreach ($newInvitees as $invitee) {
            CallParticipant::query()->create([
                'tenant_id' => $call->tenant_id,
                'call_id' => $call->id,
                'user_id' => $invitee->id,
                'status' => CallParticipantStatus::Invited,
                'invited_at' => CarbonImmutable::now(),
            ]);

            IncomingCall::dispatch($invitee->id, $call->uuid, $inviter->name, $inviter->initials(), $totalParticipants);
        }

        return $newInvitees;
    }

    public function accept(Call $call, User $user): CallParticipant
    {
        $this->guardSameTenant($call, $user);

        $participant = $this->participantOrFail($call, $user);

        if (! $call->status->isOpen()) {
            throw CallException::alreadyEnded();
        }

        $participant->forceFill([
            'status' => CallParticipantStatus::Joined,
            'joined_at' => CarbonImmutable::now(),
        ])->save();

        if ($call->status === CallStatus::Ringing) {
            $call->forceFill(['status' => CallStatus::Active, 'started_at' => CarbonImmutable::now()])->save();
        }

        return $participant;
    }

    public function decline(Call $call, User $user): void
    {
        $this->guardSameTenant($call, $user);

        $participant = $this->participantOrFail($call, $user);
        $participant->forceFill(['status' => CallParticipantStatus::Declined])->save();

        CallDeclined::dispatch($call, $user->name);
    }

    /**
     * Leave a call. If no one is left joined, the call ends for everyone.
     */
    public function leave(Call $call, User $user): void
    {
        $this->guardSameTenant($call, $user);

        $participant = $this->participantOrFail($call, $user);
        $participant->forceFill(['status' => CallParticipantStatus::Left, 'left_at' => CarbonImmutable::now()])->save();

        $stillJoined = $call->participants()->where('status', CallParticipantStatus::Joined)->exists();

        if (! $stillJoined) {
            $this->endCall($call);
        }
    }

    /**
     * End the call for every participant — a deliberate hang-up-for-all
     * action, distinct from one person simply leaving.
     */
    public function endForEveryone(Call $call): void
    {
        if (! $call->status->isOpen()) {
            return;
        }

        $this->endCall($call);
    }

    private function endCall(Call $call): void
    {
        $call->forceFill(['status' => CallStatus::Ended, 'ended_at' => CarbonImmutable::now()])->save();

        $call->participants()->where('status', CallParticipantStatus::Joined)
            ->update(['status' => CallParticipantStatus::Left->value, 'left_at' => CarbonImmutable::now()]);
        $call->participants()->where('status', CallParticipantStatus::Invited)
            ->update(['status' => CallParticipantStatus::Missed->value]);

        CallEnded::dispatch($call);
    }

    private function participantOrFail(Call $call, User $user): CallParticipant
    {
        $participant = $call->participants()->where('user_id', $user->id)->first();

        if ($participant === null) {
            throw CallException::notAParticipant();
        }

        return $participant;
    }

    private function guardSameTenant(Call $call, User $user): void
    {
        if ($user->tenant_id === null || $user->tenant_id !== $call->tenant_id) {
            throw CallException::notAParticipant();
        }
    }
}
