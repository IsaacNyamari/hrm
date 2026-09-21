<x-layouts::marketing :title="__('Link expired')">
    <x-marketing.focus-card>
        <span class="grid size-14 place-items-center rounded-2xl bg-danger/10 text-danger">
            <flux:icon.link-slash />
        </span>
        <h1 class="mt-6 text-2xl font-extrabold tracking-tight text-night">{{ __('This link is no longer valid') }}</h1>
        <p class="mt-3 text-zinc-600">
            {{ __('Account set-up links work once and expire after 7 days. If you have already chosen a password, just log in. Otherwise use "Forgot password" with your work email, or ask your HR team to resend the link.') }}
        </p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('login') }}" class="rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white transition hover:bg-indigo-700">{{ __('Log in') }}</a>
            <a href="{{ route('password.request') }}" class="rounded-xl border border-zinc-200 px-5 py-3 text-sm font-semibold text-zinc-700 transition hover:border-brand hover:text-brand">{{ __('Forgot password') }}</a>
        </div>
    </x-marketing.focus-card>
</x-layouts::marketing>
