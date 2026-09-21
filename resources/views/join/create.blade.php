@php
    $field = 'mt-1.5 block w-full rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm text-night shadow-sm transition focus:border-brand focus:outline-hidden focus:ring-4 focus:ring-brand/10';
    $label = 'block text-sm font-semibold text-night';
    $deadline = $invitation->opened_at->copy()->addMinutes(\App\Models\RegistrationInvitation::SESSION_MINUTES);
@endphp

<x-layouts::marketing :title="__('Create your workspace')">
    <x-marketing.focus-card>
        <h1 class="text-2xl font-extrabold tracking-tight text-night">{{ __('Create your workspace') }}</h1>
        <p class="mt-2 text-sm text-zinc-600">{{ __('Finish before :time. Your free trial starts as soon as you are done.', ['time' => $deadline->format('H:i')]) }}</p>

        @if ($errors->any())
            <div class="mt-5 rounded-xl bg-danger/10 p-4 text-sm text-danger">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('join.store') }}" class="mt-6 space-y-5">
            @csrf
            <div>
                <label class="{{ $label }}">{{ __('Email') }}</label>
                <input type="email" value="{{ $invitation->email }}" class="{{ $field }} bg-zinc-50 text-zinc-500" disabled>
            </div>
            <div>
                <label for="organization" class="{{ $label }}">{{ __('Organization name') }}</label>
                <input id="organization" name="organization" type="text" value="{{ old('organization', $invitation->company) }}" class="{{ $field }}" required autofocus autocomplete="organization">
            </div>
            <div>
                <label for="name" class="{{ $label }}">{{ __('Your full name') }}</label>
                <input id="name" name="name" type="text" value="{{ old('name', $invitation->name) }}" class="{{ $field }}" required autocomplete="name">
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="password" class="{{ $label }}">{{ __('Password') }}</label>
                    <input id="password" name="password" type="password" class="{{ $field }}" required autocomplete="new-password">
                </div>
                <div>
                    <label for="password_confirmation" class="{{ $label }}">{{ __('Confirm password') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="{{ $field }}" required autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-brand/25 transition hover:bg-indigo-700">
                {{ __('Create workspace') }} <flux:icon.arrow-right variant="mini" />
            </button>
        </form>
    </x-marketing.focus-card>
</x-layouts::marketing>
