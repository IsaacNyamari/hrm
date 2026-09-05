<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">
        <div class="mx-auto flex min-h-screen w-full max-w-3xl flex-col px-6">
            <header class="flex items-center justify-between py-6">
                <span class="text-sm font-semibold tracking-tight">{{ config('app.name', 'HRM') }}</span>

                <nav class="flex items-center gap-2 text-sm">
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate
                           class="rounded-md border border-neutral-300 px-4 py-1.5 hover:border-neutral-400 dark:border-neutral-700 dark:hover:border-neutral-500">
                            {{ __('Dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate
                           class="rounded-md px-4 py-1.5 hover:text-neutral-600 dark:hover:text-neutral-300">
                            {{ __('Log in') }}
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" wire:navigate
                               class="rounded-md border border-neutral-300 px-4 py-1.5 hover:border-neutral-400 dark:border-neutral-700 dark:hover:border-neutral-500">
                                {{ __('Register') }}
                            </a>
                        @endif
                    @endauth
                </nav>
            </header>

            <main class="flex flex-1 flex-col justify-center py-16">
                <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ __('Run your whole team from one place.') }}
                </h1>
                <p class="mt-4 max-w-xl text-neutral-600 dark:text-neutral-400">
                    {{ __('Employees, org structure, attendance, leave, payroll, documents and performance — a single multi-tenant HR platform for every organisation you manage.') }}
                </p>

                <div class="mt-8 flex flex-wrap gap-3 text-sm">
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate
                           class="rounded-md bg-neutral-900 px-5 py-2 font-medium text-white dark:bg-white dark:text-neutral-900">
                            {{ __('Open dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate
                           class="rounded-md bg-neutral-900 px-5 py-2 font-medium text-white dark:bg-white dark:text-neutral-900">
                            {{ __('Sign in') }}
                        </a>
                    @endauth
                </div>

                <dl class="mt-14 grid grid-cols-2 gap-x-8 gap-y-4 text-sm sm:grid-cols-3">
                    @foreach ([
                        __('People & org chart'),
                        __('Attendance & shifts'),
                        __('Leave & holidays'),
                        __('Payroll & payslips'),
                        __('Documents & letters'),
                        __('Goals & reviews'),
                    ] as $feature)
                        <dt class="text-neutral-700 dark:text-neutral-300">{{ $feature }}</dt>
                    @endforeach
                </dl>
            </main>

            <footer class="py-6 text-xs text-neutral-500">
                &copy; {{ date('Y') }} {{ config('app.name', 'HRM') }}
            </footer>
        </div>

        @fluxScripts
    </body>
</html>
