@php
    $tones = [
        'mrr' => ['bg-brand text-white shadow-brand/30', 'arrow-path'],
        'revenue_month' => ['bg-success text-white shadow-success/30', 'banknotes'],
        'tenants' => ['bg-info text-white shadow-info/30', 'building-office-2'],
        'invoices_open' => ['bg-warning text-night shadow-warning/30', 'document-text'],
    ];
    $minorIcons = [
        'active' => ['check-circle', 'text-success bg-success/10'],
        'trialing' => ['sparkles', 'text-info bg-info/10'],
        'past_due' => ['exclamation-triangle', 'text-amber-600 bg-warning/15'],
        'suspended' => ['no-symbol', 'text-danger bg-danger/10'],
        'employees' => ['users', 'text-brand bg-brand/10'],
        'users' => ['user-circle', 'text-cyan-700 bg-highlight/15 dark:text-highlight'],
    ];
    $statusColors = [
        'active' => 'var(--color-success)',
        'trialing' => 'var(--color-info)',
        'pending' => 'var(--color-highlight)',
        'past_due' => 'var(--color-warning)',
        'suspended' => 'var(--color-danger)',
        'cancelled' => 'var(--color-zinc-400)',
        'expired' => 'var(--color-zinc-600)',
    ];
    $planColors = ['var(--color-brand)', 'var(--color-highlight)', 'var(--color-info)', 'var(--color-success)', 'var(--color-warning)', 'var(--color-danger)'];

    $revenueTotal = array_sum(array_column($revenue_series, 'value'));
    $signupTotal = array_sum(array_column($signup_series, 'value'));
    $planMrrTotal = max(1, array_sum(array_column($plan_breakdown, 'mrr_cents')));
    $topHeadcount = max(1, ...array_column($top_tenants, 'employees') ?: [1]);
    $panel = 'rounded-xl border border-neutral-200 p-6 dark:border-neutral-700';
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Platform overview') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Cross-organization figures for the whole installation.') }}</flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button size="sm" icon="building-office-2" :href="route('platform.tenants.index')" wire:navigate>{{ __('Organizations') }}</flux:button>
            <flux:button size="sm" icon="rectangle-stack" :href="route('platform.plans.index')" wire:navigate>{{ __('Plans') }}</flux:button>
            <a href="{{ route('platform.billing') }}" wire:navigate class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-highlight px-3 text-sm font-semibold text-night shadow-sm shadow-highlight/30 transition hover:bg-cyan-400">
                <flux:icon.banknotes variant="micro" />
                {{ __('Billing') }}
            </a>
        </div>
    </div>

    {{-- Headline KPIs --}}
    <div class="grid auto-rows-min gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            @php([$tone, $icon] = $tones[$card['key']] ?? ['bg-brand text-white', 'chart-bar'])
            <a href="{{ $card['href'] }}" wire:navigate data-test="platform-card-{{ $card['key'] }}"
               class="group relative overflow-hidden rounded-xl p-5 shadow-lg transition duration-150 hover:-translate-y-0.5 {{ $tone }}">
                <span class="pointer-events-none absolute -end-6 -bottom-8 size-28 rounded-full bg-white/10"></span>

                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-semibold">{{ __($card['label']) }}</p>
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-white/20 ring-4 ring-white/10">
                        <flux:icon :name="$icon" variant="mini" />
                    </span>
                </div>

                <p class="mt-1 text-3xl font-bold tracking-tight">{{ $card['value'] }}</p>

                <p class="mt-2 flex flex-wrap items-center gap-1.5 text-xs font-medium">
                    @if ($card['trend'] !== null)
                        <span class="inline-flex items-center gap-0.5 rounded-full bg-white/25 px-1.5 py-0.5 font-semibold">
                            <flux:icon :name="$card['trend'] >= 0 ? 'arrow-trending-up' : 'arrow-trending-down'" variant="micro" />
                            {{ ($card['trend'] > 0 ? '+' : '').$card['trend'] }}%
                        </span>
                    @endif
                    <span class="opacity-80">{{ __($card['hint']) }}</span>
                    <flux:icon.arrow-right variant="micro" class="ms-auto opacity-70 transition group-hover:translate-x-0.5 group-hover:opacity-100" />
                </p>
            </a>
        @endforeach
    </div>

    {{-- Secondary counts --}}
    <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach ($minor_cards as $card)
            @php([$icon, $iconTone] = $minorIcons[$card['key']] ?? ['chart-bar', 'text-brand bg-brand/10'])
            <a href="{{ $card['href'] }}" wire:navigate data-test="platform-card-{{ $card['key'] }}"
               class="flex items-center gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <span class="grid size-10 shrink-0 place-items-center rounded-lg {{ $iconTone }}">
                    <flux:icon :name="$icon" variant="mini" />
                </span>
                <span class="min-w-0">
                    <span class="block text-xl font-bold leading-tight text-zinc-900 dark:text-white">{{ number_format($card['value']) }}</span>
                    <span class="block truncate text-xs text-zinc-500">{{ __($card['label']) }}</span>
                </span>
            </a>
        @endforeach
    </div>

    {{-- Revenue trend + organization status --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="{{ $panel }} lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('Revenue collected') }}</flux:heading>
                    <flux:text class="mt-0.5 text-xs">{{ __('Successful payments, last 12 months') }}</flux:text>
                </div>
                <div class="text-end">
                    <p class="text-xl font-bold text-zinc-900 dark:text-white">{{ $currency }} {{ number_format($revenueTotal / 100, 0) }}</p>
                    <a href="{{ route('platform.billing', ['tab' => 'payments']) }}" wire:navigate class="text-xs font-medium text-brand hover:underline dark:text-highlight">{{ __('View payments') }} →</a>
                </div>
            </div>
            <x-charts.bar class="mt-6 h-60" :series="$revenue_series" :prefix="$currency" :divisor="100" color="var(--color-brand)" />
        </div>

        <div class="{{ $panel }}">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('Organizations by status') }}</flux:heading>
                    <flux:text class="mt-0.5 text-xs">{{ __('Click a status to filter') }}</flux:text>
                </div>
            </div>
            <x-charts.donut class="mt-6" :caption="__('organizations')" :segments="array_map(fn (array $row): array => [
                'label' => __($row['label']),
                'value' => $row['count'],
                'color' => $statusColors[$row['status']] ?? 'var(--color-zinc-400)',
                'href' => route('platform.tenants.index', ['status' => $row['status']]),
            ], $status_breakdown)" />
        </div>
    </div>

    {{-- Growth + plan mix --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="{{ $panel }} lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('New organizations') }}</flux:heading>
                    <flux:text class="mt-0.5 text-xs">{{ __('Sign-ups per month, last 12 months') }}</flux:text>
                </div>
                <div class="text-end">
                    <p class="text-xl font-bold text-zinc-900 dark:text-white">{{ number_format($signupTotal) }}</p>
                    <a href="{{ route('platform.tenants.index') }}" wire:navigate class="text-xs font-medium text-brand hover:underline dark:text-highlight">{{ __('View organizations') }} →</a>
                </div>
            </div>
            <x-charts.line class="mt-6 h-52" :series="$signup_series" color="var(--color-highlight)" />
        </div>

        <div class="{{ $panel }}">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('Revenue by plan') }}</flux:heading>
                    <flux:text class="mt-0.5 text-xs">{{ __('Share of MRR') }} · {{ $currency }} {{ number_format($mrr_cents / 100, 0) }}</flux:text>
                </div>
                <flux:link :href="route('platform.plans.index')" wire:navigate class="text-xs">{{ __('Plans') }}</flux:link>
            </div>

            <ul class="mt-6 space-y-4">
                @forelse ($plan_breakdown as $row)
                    @php($share = round($row['mrr_cents'] / $planMrrTotal * 100))
                    @php($planColor = $planColors[$loop->index % count($planColors)])
                    <li>
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="flex items-center gap-2 font-medium text-zinc-800 dark:text-zinc-100">
                                <span class="size-2.5 rounded-full" style="background: {{ $planColor }}"></span>
                                {{ $row['plan'] }}
                            </span>
                            <span class="text-zinc-500">{{ trans_choice(':count sub|:count subs', $row['subscriptions']) }}</span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/5">
                            <div class="h-full rounded-full" style="width: {{ max(2, $share) }}%; background: {{ $planColor }}"></div>
                        </div>
                        <div class="mt-1 flex justify-between text-xs text-zinc-500">
                            <span>{{ $currency }} {{ number_format($row['mrr_cents'] / 100, 0) }}</span>
                            <span>{{ $share }}%</span>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-neutral-500">{{ __('No active subscriptions yet.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Largest + newest organizations --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="{{ $panel }}">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">{{ __('Largest organizations') }}</flux:heading>
                    <flux:text class="mt-0.5 text-xs">{{ __('By employee headcount') }}</flux:text>
                </div>
            </div>
            <ul class="mt-5 space-y-3">
                @forelse ($top_tenants as $row)
                    <li>
                        <a @if ($row['slug']) href="{{ route('platform.tenants.show', $row['slug']) }}" wire:navigate @endif class="group block">
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <span class="flex items-center gap-2 truncate font-medium text-zinc-800 group-hover:text-brand dark:text-zinc-100 dark:group-hover:text-highlight">
                                    <span class="grid size-6 place-items-center rounded-md bg-brand/10 text-[11px] font-bold text-brand dark:text-highlight">{{ $loop->iteration }}</span>
                                    {{ $row['name'] }}
                                </span>
                                <span class="shrink-0 font-semibold text-zinc-900 dark:text-white">{{ number_format($row['employees']) }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/5">
                                <div class="h-full rounded-full bg-linear-to-r from-brand to-highlight" style="width: {{ max(2, round($row['employees'] / $topHeadcount * 100)) }}%"></div>
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="text-sm text-neutral-500">{{ __('No employees recorded yet.') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="{{ $panel }}">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">{{ __('Newest organizations') }}</flux:heading>
                    <flux:text class="mt-0.5 text-xs">{{ __('Latest sign-ups') }}</flux:text>
                </div>
                <flux:link :href="route('platform.tenants.index')" wire:navigate class="text-xs">{{ __('All') }}</flux:link>
            </div>
            <ul class="mt-4 divide-y divide-neutral-100 text-sm dark:divide-neutral-800">
                @forelse ($recent_tenants as $tenant)
                    <li>
                        <a href="{{ route('platform.tenants.show', $tenant) }}" wire:navigate class="group flex items-center gap-3 py-2.5">
                            <flux:avatar size="sm" :name="$tenant->name" color="auto" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-zinc-800 group-hover:text-brand dark:text-zinc-100 dark:group-hover:text-highlight">{{ $tenant->name }}</span>
                                <span class="block text-xs text-zinc-500">{{ $tenant->subscription?->plan?->name ?? __('No plan') }} · {{ $tenant->created_at?->diffForHumans() }}</span>
                            </span>
                            <x-status-badge :status="$tenant->status" />
                        </a>
                    </li>
                @empty
                    <li class="py-3 text-neutral-500">{{ __('No organizations yet.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
