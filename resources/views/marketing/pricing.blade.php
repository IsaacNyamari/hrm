@php
    $flagLabels = [
        'hr_core' => __('Employee records & org chart'),
        'leave' => __('Leave management'),
        'attendance' => __('Attendance & biometrics'),
        'reports' => __('Reports & exports'),
        'payroll' => __('Payroll with statutory deductions'),
        'documents' => __('HR letters & documents'),
        'performance' => __('Performance & goals'),
        'recruitment' => __('Recruitment & careers page'),
        'expenses' => __('Expenses & loans'),
        'assets' => __('Asset register'),
        'api_access' => __('REST API & webhooks'),
    ];
    $featured = $plans->count() > 1 ? $plans->values()->get(1)?->id : $plans->first()?->id;
@endphp

<x-layouts::marketing :title="__('Pricing')" :description="__('Simple monthly pricing in KES for HR, payroll and attendance. Every plan starts with a free trial.')"
    :keywords="['HR software pricing Kenya', 'affordable HR software Kenya', 'cheap HRM software Kenya', 'payroll software cost Kenya', 'HR software plans Kenya', 'HRMS pricing Kenya']">
    <x-marketing.page-header
        :eyebrow="__('Pricing')"
        :title="__('Simple pricing that grows with your team')"
        :subtitle="__('Pay monthly in KES. Every plan starts with a free trial, and you can change plans at any time.')" />

    <section class="relative bg-canvas pb-24">
        <div class="mx-auto -mt-10 max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 {{ match ($plans->count()) { 1 => 'mx-auto max-w-md', 2 => 'lg:grid-cols-2', default => 'lg:grid-cols-3' } }}">
                @forelse ($plans as $plan)
                    @php($isFeatured = $plan->id === $featured)
                    <div class="relative flex flex-col rounded-3xl p-8 {{ $isFeatured ? 'bg-night text-white shadow-2xl shadow-brand/30 ring-2 ring-highlight lg:-mt-4' : 'bg-white shadow-xl shadow-brand/5' }}">
                        @if ($isFeatured)
                            <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-highlight px-4 py-1 text-xs font-bold uppercase tracking-wider text-night">{{ __('Most popular') }}</span>
                        @endif
                        <h2 class="text-xl font-bold {{ $isFeatured ? 'text-white' : 'text-night' }}">{{ $plan->name }}</h2>
                        <p class="mt-2 min-h-10 text-sm {{ $isFeatured ? 'text-zinc-300' : 'text-zinc-500' }}">{{ $plan->description }}</p>
                        <p class="mt-6 flex items-baseline gap-1">
                            <span class="text-sm font-semibold {{ $isFeatured ? 'text-zinc-300' : 'text-zinc-500' }}">{{ $plan->currency }}</span>
                            <span class="text-5xl font-extrabold tracking-tight">{{ number_format($plan->price_cents / 100) }}</span>
                            <span class="text-sm {{ $isFeatured ? 'text-zinc-300' : 'text-zinc-500' }}">{{ $plan->billing_interval->perLabel() }}</span>
                        </p>
                        <p class="mt-2 text-sm font-semibold {{ $isFeatured ? 'text-highlight' : 'text-brand' }}">
                            {{ $plan->employee_limit === null ? __('Unlimited employees') : __('Up to :count employees', ['count' => $plan->employee_limit]) }}
                            @if ($plan->trial_days > 0)
                                · {{ __(':days-day free trial', ['days' => $plan->trial_days]) }}
                            @endif
                        </p>

                        <ul class="mt-8 flex-1 space-y-3 text-sm">
                            @foreach ((array) ($plan->features['flags'] ?? []) as $flag)
                                <li class="flex gap-2.5">
                                    <flux:icon.check-circle variant="mini" class="size-5 shrink-0 {{ $isFeatured ? 'text-highlight' : 'text-success' }}" />
                                    {{ $flagLabels[$flag] ?? str($flag)->headline() }}
                                </li>
                            @endforeach
                        </ul>

                        <a href="{{ route('quote', ['plan' => $plan->slug]) }}"
                           class="mt-10 block rounded-xl px-6 py-3 text-center text-sm font-bold transition {{ $isFeatured ? 'bg-highlight text-night hover:bg-cyan-300' : 'bg-brand text-white hover:bg-indigo-700' }}">
                            {{ __('Request a quote') }}
                        </a>
                    </div>
                @empty
                    <div class="rounded-3xl bg-white p-10 text-center shadow-xl lg:col-span-3">
                        <p class="text-lg font-semibold text-night">{{ __('Pricing is tailored to your organization.') }}</p>
                        <a href="{{ route('quote') }}" class="mt-4 inline-block font-bold text-brand hover:underline">{{ __('Request a quote') }} →</a>
                    </div>
                @endforelse
            </div>

            <div class="mt-16 grid gap-6 rounded-3xl bg-white p-8 shadow-sm md:grid-cols-3">
                @foreach ([
                    ['building-office', __('Larger organization?'), __('Over a few hundred employees or several legal entities? We will tailor a plan and onboarding for you.')],
                    ['credit-card', __('Easy payment'), __('Pay by card, M-Pesa or bank transfer through our secure payment provider.')],
                    ['arrow-path', __('No lock-in'), __('Monthly billing. Upgrade, downgrade or cancel whenever you need to.')],
                ] as [$icon, $title, $text])
                    <div class="flex gap-4">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand/10 text-brand"><flux:icon :name="$icon" variant="mini" /></span>
                        <div>
                            <h3 class="font-bold text-night">{{ $title }}</h3>
                            <p class="mt-1 text-sm text-zinc-500">{{ $text }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
</x-layouts::marketing>
