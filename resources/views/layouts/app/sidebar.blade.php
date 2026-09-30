<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased dark:bg-zinc-950 dark:text-zinc-100 {{ session()->has('impersonator_id') ? 'pt-9' : '' }}">
        @if (session()->has('impersonator_id'))
            <div class="fixed inset-x-0 top-0 z-50 flex h-9 items-center justify-center gap-3 bg-amber-500 px-4 text-sm font-medium text-amber-950 shadow-md">
                <span>{{ __('You are impersonating') }} {{ auth()->user()?->name }}.</span>
                <form method="POST" action="{{ route('impersonate.stop') }}">
                    @csrf
                    <button type="submit" class="rounded bg-amber-950/10 px-2 py-0.5 underline">{{ __('Stop impersonating') }}</button>
                </form>
            </div>
        @endif

        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200/80 bg-linear-to-b from-white via-white to-indigo-100 dark:border-white/10 dark:from-zinc-900 dark:via-zinc-900 dark:to-indigo-950">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <div class="flex items-center gap-3 rounded-xl px-2 py-3">
                <flux:avatar circle :name="auth()->user()->name" :initials="auth()->user()->initials()" color="indigo" class="ring-2 ring-brand/15" />
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ auth()->user()->isPlatformAdmin() ? __('Platform admin') : str(auth()->user()->getRoleNames()->first() ?? __('Member'))->headline() }}</p>
                </div>
                <a href="{{ route('profile.edit') }}" wire:navigate class="grid size-6 place-items-center rounded-md bg-highlight text-white shadow-sm shadow-highlight/30 transition hover:bg-cyan-600" aria-label="{{ __('Edit profile') }}">
                    <flux:icon.pencil-square variant="micro" />
                </a>
            </div>

            <flux:sidebar.nav>
                @if (auth()->user()?->isPlatformAdmin())
                    <flux:sidebar.group :heading="__('Platform')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('platform.dashboard')" :current="request()->routeIs('platform.dashboard')" wire:navigate>
                            {{ __('Overview') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="building-office-2" :href="route('platform.tenants.index')" :current="request()->routeIs('platform.tenants.*')" wire:navigate>
                            {{ __('Organizations') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="rectangle-stack" :href="route('platform.plans.index')" :current="request()->routeIs('platform.plans.*')" wire:navigate>
                            {{ __('Plans') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="banknotes" :href="route('platform.billing')" :current="request()->routeIs('platform.billing') || request()->routeIs('platform.billing.*')" wire:navigate>
                            {{ __('Billing') }}
                        </flux:sidebar.item>
                        @if (auth()->user()->canOnPlatform(\App\Enums\PlatformPermission::LeadsManage))
                            <flux:sidebar.item icon="inbox-arrow-down" :href="route('platform.leads')" :current="request()->routeIs('platform.leads')" wire:navigate>
                                {{ __('Leads') }}
                            </flux:sidebar.item>
                        @endif
                        @if (auth()->user()->canOnPlatform(\App\Enums\PlatformPermission::SettingsManage))
                            <flux:sidebar.item icon="cog-6-tooth" :href="route('platform.settings')" :current="request()->routeIs('platform.settings')" wire:navigate>
                                {{ __('Settings') }}
                            </flux:sidebar.item>
                        @endif
                        @if (auth()->user()->canOnPlatform(\App\Enums\PlatformPermission::DeploymentsManage))
                            <flux:sidebar.item icon="rocket-launch" :href="route('platform.deployments')" :current="request()->routeIs('platform.deployments')" wire:navigate>
                                {{ __('Deployments') }}
                            </flux:sidebar.item>
                        @endif
                        @if (auth()->user()->canOnPlatform(\App\Enums\PlatformPermission::SitemapManage))
                            <flux:sidebar.item icon="map" :href="route('platform.sitemap')" :current="request()->routeIs('platform.sitemap')" wire:navigate>
                                {{ __('Sitemap') }}
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                @elseif (auth()->user()->isSelfServiceOnly())
                <flux:sidebar.group :heading="__('Me')" class="grid">
                    <flux:sidebar.item icon="user-circle" :href="route('portal')" :current="request()->routeIs('portal')" wire:navigate>
                        {{ __('My HR') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clock" :href="route('attendance.index')" :current="request()->routeIs('attendance.*')" wire:navigate>
                        {{ __('Time clock') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="calendar-days" :href="route('leave')" :current="request()->routeIs('leave')" wire:navigate>
                        {{ __('My leave') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-check" :href="route('tasks')" :current="request()->routeIs('tasks')" wire:navigate>
                        {{ __('My tasks') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="ticket" :href="route('hr-requests')" :current="request()->routeIs('hr-requests')" wire:navigate>
                        {{ __('HR requests') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="video-camera" :href="route('meetings')" :current="request()->routeIs('meetings')" wire:navigate>
                        {{ __('Meetings') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="key" :href="route('permissions.mine')" :current="request()->routeIs('permissions.mine')" wire:navigate>
                        {{ __('My permissions') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="bell" :href="route('notifications')" :current="request()->routeIs('notifications')" wire:navigate>
                        {{ __('Notifications') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @else
                <flux:sidebar.group :heading="__('Overview')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-circle" :href="route('portal')" :current="request()->routeIs('portal')" wire:navigate>
                        {{ __('My HR') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="ticket" :href="route('hr-requests')" :current="request()->routeIs('hr-requests')" wire:navigate>
                        {{ __('HR requests') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="video-camera" :href="route('meetings')" :current="request()->routeIs('meetings')" wire:navigate>
                        {{ __('Meetings') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar" :href="route('reports')" :current="request()->routeIs('reports')" wire:navigate>
                        {{ __('Reports') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="bell" :href="route('notifications')" :current="request()->routeIs('notifications')" wire:navigate>
                        {{ __('Notifications') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="code-bracket" :href="route('developers')" :current="request()->routeIs('developers')" wire:navigate>
                        {{ __('Developers') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="academic-cap" :href="route('training')" :current="request()->routeIs('training')" wire:navigate>
                        {{ __('Training') }}
                    </flux:sidebar.item>
                    @can('audit.view')
                        <flux:sidebar.item icon="shield-check" :href="route('audit')" :current="request()->routeIs('audit')" wire:navigate>
                            {{ __('Audit trail') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('billing.view')
                        <flux:sidebar.item icon="wallet" :href="route('billing.index')" :current="request()->routeIs('billing.index')" wire:navigate>
                            {{ __('Billing') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('People')" class="grid">
                    <flux:sidebar.item icon="users" :href="route('employees.index')" :current="request()->routeIs('employees.*')" wire:navigate>
                        {{ __('Employees') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="rectangle-group" :href="route('org-chart')" :current="request()->routeIs('org-chart')" wire:navigate>
                        {{ __('Organogram') }}
                    </flux:sidebar.item>
                    @can('roles.manage')
                        <flux:sidebar.item icon="key" :href="route('settings.permissions')" :current="request()->routeIs('settings.permissions')" wire:navigate>
                            {{ __('Permissions') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Time & leave')" class="grid">
                    <flux:sidebar.item icon="clock" :href="route('attendance.index')" :current="request()->routeIs('attendance.*')" wire:navigate>
                        {{ __('Attendance') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="calendar-days" :href="route('leave')" :current="request()->routeIs('leave')" wire:navigate>
                        {{ __('Leave') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Work')" class="grid">
                    <flux:sidebar.item icon="clipboard-document-check" :href="route('tasks')" :current="request()->routeIs('tasks')" wire:navigate>
                        {{ __('Tasks') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="banknotes" :href="route('payroll')" :current="request()->routeIs('payroll')" wire:navigate>
                        {{ __('Payroll') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="trophy" :href="route('performance')" :current="request()->routeIs('performance')" wire:navigate>
                        {{ __('Performance') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-plus" :href="route('recruitment')" :current="request()->routeIs('recruitment')" wire:navigate>
                        {{ __('Recruitment') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('hr-letters')" :current="request()->routeIs('hr-letters')" wire:navigate>
                        {{ __('HR letters') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Finance')" class="grid">
                    <flux:sidebar.item icon="receipt-percent" :href="route('expenses')" :current="request()->routeIs('expenses')" wire:navigate>
                        {{ __('Expenses') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="credit-card" :href="route('loan-centre')" :current="request()->routeIs('loan-centre')" wire:navigate>
                        {{ __('Loans') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="computer-desktop" :href="route('assets')" :current="request()->routeIs('assets')" wire:navigate>
                        {{ __('Assets') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

        </flux:sidebar>

        <flux:header sticky class="gap-2 bg-night text-white shadow-sm lg:gap-3 dark:bg-zinc-950">
            <flux:sidebar.toggle class="text-white! lg:hidden" icon="bars-2" inset="left" />

            @if (! auth()->user()->isPlatformAdmin())
                @can('employees.view')
                    <form method="GET" action="{{ route('employees.index') }}" class="relative hidden w-full max-w-xs sm:block">
                        <flux:icon.magnifying-glass variant="micro" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-white/50" />
                        <input type="search" name="q" placeholder="{{ __('Search employees…') }}"
                               class="w-full rounded-lg border-0 bg-white/10 py-2 ps-9 pe-3 text-sm text-white placeholder:text-white/50 focus:bg-white/15 focus:outline-hidden focus:ring-2 focus:ring-highlight/60" />
                    </form>
                @endcan
            @endif

            <flux:spacer />

            @unless (auth()->user()->isPlatformAdmin())
                <livewire:calls.incoming-call-listener />
            @endunless

            <livewire:notifications.bell />

            <flux:dropdown position="bottom" align="end">
                <button type="button" class="flex items-center gap-2 rounded-lg px-1.5 py-1 text-sm font-medium text-white transition hover:bg-white/10" data-test="sidebar-menu-button">
                    <flux:avatar circle size="sm" :name="auth()->user()->name" :initials="auth()->user()->initials()" color="cyan" />
                    <span class="hidden max-w-40 truncate md:inline">{{ auth()->user()->name }}</span>
                    <flux:icon.chevron-down variant="micro" class="text-white/60" />
                </button>

                <flux:menu>
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar
                            :name="auth()->user()->name"
                            :initials="auth()->user()->initials()"
                        />

                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                            <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @stack('scripts')

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
