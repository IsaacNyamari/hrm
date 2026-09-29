<div wire:ignore x-data="{
        ringing: null,
        boot() {
            window.Echo.private('App.Models.User.{{ $authUserId }}')
                .listen('.call.incoming', (e) => {
                    this.ringing = e;
                });
        },
        accept() {
            const uuid = this.ringing.call_uuid;
            this.ringing = null;
            this.$wire.accept(uuid);
        },
        decline() {
            this.$wire.decline(this.ringing.call_uuid);
            this.ringing = null;
        },
    }" x-init="boot()">
    <div x-show="ringing" x-cloak
         class="fixed bottom-6 end-6 z-50 flex w-80 flex-col gap-3 rounded-xl border border-neutral-200 bg-white p-4 shadow-xl dark:border-neutral-700 dark:bg-neutral-900">
        <div class="flex items-center gap-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand/10 text-sm font-bold text-brand dark:text-highlight" x-text="ringing?.caller_initials"></span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-neutral-800 dark:text-neutral-100" x-text="ringing?.caller_name"></p>
                <p class="text-xs text-neutral-500">
                    <span x-show="(ringing?.participant_count ?? 1) > 2">{{ __('Meeting call') }}</span>
                    <span x-show="(ringing?.participant_count ?? 1) <= 2">{{ __('Incoming call') }}</span>
                </p>
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <flux:button size="sm" variant="ghost" @click="decline()">{{ __('Decline') }}</flux:button>
            <flux:button size="sm" variant="primary" @click="accept()">{{ __('Accept') }}</flux:button>
        </div>
    </div>
</div>
