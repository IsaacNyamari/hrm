<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Overview')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-circle" :href="route('portal')" :current="request()->routeIs('portal')" wire:navigate>
                        {{ __('My HR') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('People')" class="grid">
                    <flux:sidebar.item icon="users" :href="route('employees.index')" :current="request()->routeIs('employees.*')" wire:navigate>
                        {{ __('Employees') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="rectangle-group" :href="route('org-chart')" :current="request()->routeIs('org-chart')" wire:navigate>
                        {{ __('Org chart') }}
                    </flux:sidebar.item>
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
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
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
                        </div>
                    </flux:menu.radio.group>

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

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
