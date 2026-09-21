@php
    $field = 'mt-1.5 block w-full rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm text-night shadow-sm transition focus:border-brand focus:outline-hidden focus:ring-4 focus:ring-brand/10';
    $label = 'block text-sm font-semibold text-night';
@endphp

<x-layouts::marketing :title="__('Set up your account')">
    <x-marketing.focus-card>
        <span class="grid size-14 place-items-center rounded-2xl bg-success/10 text-success">
            <flux:icon.sparkles />
        </span>
        <h1 class="mt-6 text-2xl font-extrabold tracking-tight text-night">{{ __('Welcome, :name!', ['name' => strtok($user->name, ' ')]) }}</h1>
        <p class="mt-2 text-sm text-zinc-600">{{ __('Choose a password to finish setting up your staff account. You will sign in with :email.', ['email' => $user->email]) }}</p>

        @if ($errors->any())
            <div class="mt-5 rounded-xl bg-danger/10 p-4 text-sm text-danger">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $action }}" class="mt-6 space-y-5">
            @csrf
            <div>
                <label for="password" class="{{ $label }}">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" class="{{ $field }}" required autofocus autocomplete="new-password">
            </div>
            <div>
                <label for="password_confirmation" class="{{ $label }}">{{ __('Confirm password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="{{ $field }}" required autocomplete="new-password">
            </div>
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-brand/25 transition hover:bg-indigo-700">
                {{ __('Open my dashboard') }} <flux:icon.arrow-right variant="mini" />
            </button>
        </form>
    </x-marketing.focus-card>
</x-layouts::marketing>
