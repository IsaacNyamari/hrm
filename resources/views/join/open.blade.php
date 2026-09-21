<x-layouts::marketing :title="__('Create your workspace')">
    <x-marketing.focus-card>
        <span class="grid size-14 place-items-center rounded-2xl bg-linear-to-br from-brand to-highlight text-white shadow-lg shadow-brand/30">
            <flux:icon.building-office-2 />
        </span>
        <h1 class="mt-6 text-2xl font-extrabold tracking-tight text-night">{{ __('Your access is ready') }}</h1>
        <p class="mt-3 text-zinc-600">
            {{ __('This link lets :email create an organization workspace.', ['email' => $invitation->email]) }}
        </p>
        <div class="mt-6 flex gap-3 rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm text-zinc-700">
            <flux:icon.exclamation-triangle variant="mini" class="shrink-0 text-amber-600" />
            <span>{{ __('This link works only once. After you continue it expires, and you will have :minutes minutes in this browser to finish signing up.', ['minutes' => \App\Models\RegistrationInvitation::SESSION_MINUTES]) }}</span>
        </div>
        <form method="POST" action="{{ route('join.claim', $token) }}" class="mt-8">
            @csrf
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-brand/25 transition hover:bg-indigo-700" data-test="claim-link">
                {{ __('Create my workspace') }} <flux:icon.arrow-right variant="mini" />
            </button>
        </form>
    </x-marketing.focus-card>
</x-layouts::marketing>
