<div wire:ignore x-data="callRoom(@js([
        'userId' => $authUserId,
        'userName' => $authUserName,
        'userInitials' => $authUserInitials,
        'tenantId' => $tenantId,
        'initialCallUuid' => $initialCallUuid,
    ]))" x-init="boot()" class="flex flex-col gap-6">

    <div>
        <flux:heading size="xl">{{ __('Calls') }}</flux:heading>
        <flux:text class="mt-1">{{ __('See who\'s online and start a call — pick one person for a 1:1, or a few for a quick meeting.') }}</flux:text>
    </div>

    {{-- Lobby: nobody in a call yet --}}
    <div x-show="! inCall && ! joining" x-cloak class="flex flex-col gap-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <template x-for="user in onlineUsers" :key="user.id">
                <button type="button" @click="toggleSelected(user.id)"
                    class="flex items-center gap-3 rounded-xl border p-3 text-left transition"
                    :class="selectedIds.includes(user.id) ? 'border-brand ring-2 ring-brand/30' : 'border-neutral-200 dark:border-neutral-700'">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand/10 text-sm font-bold text-brand dark:text-highlight" x-text="user.initials"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-neutral-800 dark:text-neutral-100" x-text="user.name"></span>
                        <span class="flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400">
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                            {{ __('Online') }}
                        </span>
                    </span>
                </button>
            </template>

            <p x-show="onlineUsers.length === 0" class="col-span-full rounded-xl border border-dashed border-neutral-300 p-8 text-center text-neutral-500 dark:border-neutral-700">
                {{ __('No one else is online right now.') }}
            </p>
        </div>

        <div class="flex justify-end">
            <flux:button variant="primary" @click="startCall()" x-bind:disabled="selectedIds.length === 0">
                <span x-text="selectedIds.length > 1 ? '{{ __('Start meeting') }} (' + selectedIds.length + ')' : '{{ __('Call') }}'"></span>
            </flux:button>
        </div>
    </div>

    {{-- Connecting to the call (getting camera/mic access, joining the room) --}}
    <div x-show="joining" x-cloak class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-neutral-300 p-12 text-center dark:border-neutral-700">
        <flux:icon.phone class="size-8 animate-pulse text-brand" />
        <p class="text-sm text-neutral-500">{{ __('Connecting…') }}</p>
        <p x-show="mediaError" x-text="mediaError" class="max-w-sm text-sm text-red-500"></p>
    </div>

    {{-- Ringing out: we've joined, waiting for someone to answer --}}
    <div x-show="inCall && ringingOut" x-cloak class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-neutral-300 p-12 text-center dark:border-neutral-700">
        <span class="relative flex size-16 items-center justify-center rounded-full bg-brand/10 text-brand dark:text-highlight">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand/20"></span>
            <flux:icon.phone class="relative size-7" />
        </span>
        <p class="text-sm font-medium text-neutral-700 dark:text-neutral-200">{{ __('Calling…') }}</p>
        <flux:button size="sm" variant="danger" @click="leaveCall()">{{ __('Cancel') }}</flux:button>
    </div>

    {{-- In an active call --}}
    <div x-show="inCall && ! ringingOut" x-cloak class="flex flex-col gap-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="relative aspect-video overflow-hidden rounded-xl bg-neutral-900">
                <video id="local-video" autoplay playsinline muted class="h-full w-full object-cover"></video>
                <span class="absolute bottom-2 left-2 rounded bg-black/60 px-2 py-0.5 text-xs text-white">{{ __('You') }}</span>
            </div>

            <template x-for="p in Object.values(participants)" :key="p.id">
                <div class="relative aspect-video overflow-hidden rounded-xl bg-neutral-900">
                    <video :id="'remote-video-' + p.id" autoplay playsinline class="h-full w-full object-cover" x-show="p.hasStream"></video>
                    <div x-show="! p.hasStream" class="absolute inset-0 grid place-items-center">
                        <span class="grid size-14 place-items-center rounded-full bg-brand/20 text-lg font-bold text-brand" x-text="p.initials"></span>
                    </div>
                    <span class="absolute bottom-2 left-2 rounded bg-black/60 px-2 py-0.5 text-xs text-white" x-text="p.name"></span>
                </div>
            </template>
        </div>

        <div class="relative flex flex-wrap items-center justify-center gap-2 rounded-full border border-neutral-200 bg-white/80 px-3 py-2 shadow-sm backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/80">
            <button type="button" @click="toggleMic()"
                class="relative grid size-11 shrink-0 place-items-center rounded-full transition"
                :class="micMuted ? 'bg-danger text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-white/10 dark:text-white dark:hover:bg-white/20'"
                x-bind:aria-label="micMuted ? '{{ __('Unmute') }}' : '{{ __('Mute') }}'"
                x-bind:title="micMuted ? '{{ __('Unmute') }}' : '{{ __('Mute') }}'">
                <flux:icon.microphone class="size-5" />
                <span x-show="micMuted" x-cloak class="absolute h-6 w-0.5 rotate-45 rounded-full bg-current"></span>
            </button>
            <button type="button" @click="toggleCamera()"
                class="relative grid size-11 shrink-0 place-items-center rounded-full transition"
                :class="cameraOff ? 'bg-danger text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-white/10 dark:text-white dark:hover:bg-white/20'"
                x-bind:aria-label="cameraOff ? '{{ __('Turn camera on') }}' : '{{ __('Turn camera off') }}'"
                x-bind:title="cameraOff ? '{{ __('Turn camera on') }}' : '{{ __('Turn camera off') }}'">
                <flux:icon.video-camera class="size-5" x-show="! cameraOff" />
                <flux:icon.video-camera-slash class="size-5" x-show="cameraOff" x-cloak />
            </button>

            <div class="mx-1 h-8 w-px bg-neutral-200 dark:bg-neutral-700"></div>

            <flux:button size="sm" variant="ghost" icon="user-plus" @click="showInvite = ! showInvite">{{ __('') }}</flux:button>

            <flux:button size="sm" variant="danger" icon="phone-x-mark" @click="leaveCall()">{{ __('') }}</flux:button>
            <flux:button size="sm" variant="danger" icon="phone-x-mark" x-show="isInitiator" @click="endForEveryone()">{{ __('End') }}</flux:button>

            <div x-show="showInvite" x-cloak @click.outside="showInvite = false"
                 class="absolute bottom-full mb-2 w-72 rounded-xl border border-neutral-200 bg-white p-3 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                <p class="mb-2 text-xs font-semibold uppercase text-neutral-500">{{ __('Add someone online') }}</p>
                <div class="flex max-h-48 flex-col gap-1 overflow-y-auto">
                    <template x-for="user in invitableUsers" :key="user.id">
                        <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800">
                            <input type="checkbox" :checked="inviteSelectedIds.includes(user.id)" @change="toggleInviteSelected(user.id)" />
                            <span x-text="user.name"></span>
                        </label>
                    </template>
                    <p x-show="invitableUsers.length === 0" class="px-2 py-1.5 text-sm text-neutral-500">{{ __('No one else is online.') }}</p>
                </div>
                <div class="mt-2 flex justify-end">
                    <flux:button size="sm" variant="primary" @click="inviteMore()" x-bind:disabled="inviteSelectedIds.length === 0">{{ __('Invite') }}</flux:button>
                </div>
            </div>
        </div>
    </div>
</div>
