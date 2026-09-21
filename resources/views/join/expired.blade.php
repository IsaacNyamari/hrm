<x-layouts::marketing :title="__('Link expired')">
    <x-marketing.focus-card>
        <span class="grid size-14 place-items-center rounded-2xl bg-danger/10 text-danger">
            <flux:icon.link-slash />
        </span>
        <h1 class="mt-6 text-2xl font-extrabold tracking-tight text-night">{{ __('This link is no longer valid') }}</h1>
        <p class="mt-3 text-zinc-600">
            {{ __('Access links work only once and expire after they are opened (or after a few days if unused). If you did not finish creating your workspace, request a new link and we will send it to you.') }}
        </p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('contact') }}" class="rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white transition hover:bg-indigo-700">{{ __('Request a new link') }}</a>
            <a href="{{ route('login') }}" class="rounded-xl border border-zinc-200 px-5 py-3 text-sm font-semibold text-zinc-700 transition hover:border-brand hover:text-brand">{{ __('Log in') }}</a>
        </div>
    </x-marketing.focus-card>
</x-layouts::marketing>
