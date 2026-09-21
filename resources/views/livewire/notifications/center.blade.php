<div class="flex flex-col gap-6" wire:poll.30s>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-neutral-800 dark:text-neutral-100">
            {{ __('Notifications') }}
            @if ($unread > 0)
                <span class="ml-1 rounded-full bg-blue-600 px-2 py-0.5 text-xs text-white">{{ $unread }}</span>
            @endif
        </h1>
        <div class="flex items-center gap-2 text-sm">
            <label class="flex items-center gap-1 text-neutral-500">
                <input type="checkbox" wire:model.live="unreadOnly"> {{ __('Unread only') }}
            </label>
            <button type="button" wire:click="markAllRead" class="rounded-lg border border-neutral-300 px-3 py-1.5 dark:border-neutral-700">{{ __('Mark all read') }}</button>
        </div>
    </div>

    <ul class="divide-y divide-neutral-100 rounded-xl border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
        @forelse ($notifications as $n)
            <li class="flex items-start justify-between gap-4 px-4 py-3 {{ $n->read_at === null ? 'bg-blue-50/50 dark:bg-blue-950/20' : '' }}">
                <div>
                    <p class="text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ $headline($n->data) }}</p>
                    <p class="text-xs text-neutral-500">
                        {{ \Illuminate\Support\Arr::get($n->data, 'subject') ?? \Illuminate\Support\Arr::get($n->data, 'reference') ?? '' }}
                        · {{ $n->created_at?->diffForHumans() }}
                    </p>
                </div>
                @if ($n->read_at === null)
                    <button type="button" wire:click="markRead('{{ $n->id }}')" class="shrink-0 text-xs text-blue-600 hover:underline">{{ __('Mark read') }}</button>
                @endif
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-neutral-500">{{ __('Nothing here yet.') }}</li>
        @endforelse
    </ul>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
        <h2 class="text-sm font-semibold">{{ __('Email preferences') }}</h2>
        <p class="mt-1 text-xs text-neutral-500">{{ __('In-app notifications always appear here. Toggle whether we also email you.') }}</p>
        <ul class="mt-3 space-y-1.5 text-sm">
            @foreach ($categories as $category)
                @php($enabled = ($prefs->get($category->value)?->channels['mail'] ?? true) === true)
                <li class="flex items-center justify-between">
                    <span class="text-neutral-700 dark:text-neutral-300">{{ $category->label() }}</span>
                    <button type="button" wire:click="toggleMail('{{ $category->value }}')"
                        class="rounded-full px-3 py-1 text-xs {{ $enabled ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300' : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800' }}">
                        {{ $enabled ? __('Email on') : __('Email off') }}
                    </button>
                </li>
            @endforeach
        </ul>
    </div>
</div>
