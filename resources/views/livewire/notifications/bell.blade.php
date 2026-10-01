<a href="{{ route(auth()->user()?->isPlatformAdmin() ? 'platform.notifications' : 'notifications') }}" wire:navigate class="relative grid size-9 place-items-center rounded-lg bg-white/10 text-white transition hover:bg-white/20" aria-label="{{ __('Notifications') }}">
    <flux:icon.bell variant="mini" />
    @if ($unread > 0)
        <span class="absolute -end-1 -top-1 grid min-w-4 place-items-center rounded-full bg-warning px-1 text-[10px] font-bold leading-4 text-night">{{ $unread > 9 ? '9+' : $unread }}</span>
    @endif
</a>
