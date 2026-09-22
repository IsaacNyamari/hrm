@props([
    'title' => null,
    'description' => null,
])

@php
    $appName = config('app.name', 'HRM');
    $pageTitle = filled($title) ? $title.' — '.$appName : $appName.' — HR, payroll & attendance software';
    $metaDescription = $description ?? __('HR, payroll, attendance, leave and recruitment in one secure platform built for growing organizations in Kenya and beyond.');
    $links = [
        ['home', __('Home')],
        ['features', __('Features')],
        ['pricing', __('Pricing')],
        ['about', __('About')],
        ['jobs.index', __('Jobs')],
        ['applications.track', __('Track application')],
        ['docs', __('Docs')],
        ['contact', __('Contact')],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $metaDescription }}" />
        <meta property="og:title" content="{{ $pageTitle }}" />
        <meta property="og:description" content="{{ $metaDescription }}" />
        <meta property="og:type" content="website" />
        <meta property="og:url" content="{{ url()->current() }}" />
        <meta name="theme-color" content="#423a8e" />

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-white font-sans text-ink antialiased">
        {{-- Navigation --}}
        <header x-data="{ open: false, scrolled: false }" x-init="scrolled = window.scrollY > 8"
                @scroll.window="scrolled = window.scrollY > 8"
                :class="scrolled || open ? 'bg-white/90 shadow-sm backdrop-blur-md' : 'bg-transparent'"
                class="fixed inset-x-0 top-0 z-50 transition duration-300">
            <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="{{ $appName }}">
                    <span class="grid size-9 place-items-center rounded-lg bg-linear-to-br from-brand to-highlight shadow-md shadow-brand/20">
                        <x-app-logo-icon class="size-6 text-white" />
                    </span>
                    <span class="text-base font-extrabold tracking-tight transition" :class="scrolled || open ? 'text-night' : 'text-white'">{{ $appName }}</span>
                </a>

                <div class="hidden items-center gap-1 xl:flex">
                    @foreach ($links as [$route, $label])
                        <a href="{{ route($route) }}"
                           class="rounded-lg px-3 py-2 text-sm font-medium transition"
                           :class="scrolled ? '{{ request()->routeIs($route) ? 'text-brand' : 'text-zinc-600 hover:bg-brand/5 hover:text-brand' }}' : '{{ request()->routeIs($route) ? 'text-highlight' : 'text-white hover:bg-white/10' }}'">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <div class="hidden items-center gap-2 xl:flex">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white shadow-md shadow-brand/25 transition hover:bg-indigo-700">{{ __('Open dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-sm font-semibold transition" :class="scrolled ? 'text-zinc-700 hover:text-brand' : 'text-white hover:bg-white/10'">{{ __('Log in') }}</a>
                        <a href="{{ route('quote') }}" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white shadow-md shadow-brand/25 transition hover:bg-indigo-700">{{ __('Request a quote') }}</a>
                    @endauth
                </div>

                <button type="button" @click="open = !open" class="grid size-10 place-items-center rounded-lg transition xl:hidden" :class="scrolled || open ? 'text-zinc-700 hover:bg-zinc-100' : 'text-white hover:bg-white/10'" :aria-expanded="open" aria-label="{{ __('Menu') }}">
                    <flux:icon.bars-3 x-show="!open" />
                    <flux:icon.x-mark x-show="open" x-cloak />
                </button>
            </nav>

            <div x-show="open" x-cloak x-transition.origin.top class="border-t border-zinc-100 bg-white px-4 pb-6 pt-2 shadow-lg xl:hidden">
                @foreach ($links as [$route, $label])
                    <a href="{{ route($route) }}" class="block rounded-lg px-3 py-2.5 text-base font-medium {{ request()->routeIs($route) ? 'bg-brand/5 text-brand' : 'text-zinc-700' }}">{{ $label }}</a>
                @endforeach
                <div class="mt-4 grid gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-lg bg-brand px-4 py-2.5 text-center text-sm font-semibold text-white">{{ __('Open dashboard') }}</a>
                    @else
                        <a href="{{ route('quote') }}" class="rounded-lg bg-brand px-4 py-2.5 text-center text-sm font-semibold text-white">{{ __('Request a quote') }}</a>
                        <a href="{{ route('login') }}" class="rounded-lg border border-zinc-200 px-4 py-2.5 text-center text-sm font-semibold text-zinc-700">{{ __('Log in') }}</a>
                    @endauth
                </div>
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        {{-- Footer --}}
        <footer class="bg-night text-zinc-400">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-6">
                    <div class="lg:col-span-2">
                        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                            <span class="grid size-9 place-items-center rounded-lg bg-linear-to-br from-brand to-highlight">
                                <x-app-logo-icon class="size-6 text-white" />
                            </span>
                            <span class="text-base font-extrabold text-white">{{ $appName }}</span>
                        </a>
                        <p class="mt-4 max-w-sm text-sm leading-relaxed">
                            {{ __('One secure home for your people: records, attendance, leave, payroll, performance and hiring — with Kenyan statutory deductions built in.') }}
                        </p>
                        <a href="{{ route('quote') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-highlight px-4 py-2 text-sm font-semibold text-night transition hover:bg-cyan-400">
                            {{ __('Request a quote') }} <flux:icon.arrow-right variant="micro" />
                        </a>
                    </div>

                    @foreach ([
                        __('Product') => [['features', __('Features')], ['pricing', __('Pricing')], ['docs', __('Documentation')]],
                        __('Company') => [['about', __('About us')], ['contact', __('Contact')], ['quote', __('Request a quote')], ['privacy', __('Privacy policy')]],
                        __('Careers') => [['jobs.index', __('Open jobs')], ['applications.track', __('Track application')]],
                        __('Account') => [['login', __('Log in')], ['password.request', __('Reset password')]],
                    ] as $heading => $items)
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wider text-white">{{ $heading }}</p>
                            <ul class="mt-4 space-y-2.5 text-sm">
                                @foreach ($items as [$route, $label])
                                    @if (Route::has($route))
                                        <li><a href="{{ route($route) }}" class="transition hover:text-highlight">{{ $label }}</a></li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>

                <div class="mt-14 flex flex-col items-start justify-between gap-4 border-t border-white/10 pt-8 text-xs sm:flex-row sm:items-center">
                    <p>&copy; {{ now()->year }} {{ $appName }}. {{ __('All rights reserved.') }}</p>
                    <p class="flex items-center gap-2">
                        <flux:icon.lock-closed variant="micro" class="text-highlight" />
                        {{ __('HTTPS everywhere · Sensitive fields encrypted · Role-based access · Full audit trail') }}
                    </p>
                </div>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
