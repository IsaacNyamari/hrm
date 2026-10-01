<div class="flex flex-col gap-6" @if ($hasRunning) wire:poll.3s @endif>
    <div>
        <flux:heading size="xl">{{ __('Deployments') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Pull the latest code from git, or roll back to a previous commit. Runs in the background — this page updates automatically.') }}</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Branch') }}</p>
            <p class="mt-2 font-mono text-sm">{{ $currentBranch }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 sm:col-span-2">
            <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Current commit') }}</p>
            <p class="mt-2 font-mono text-sm">{{ $currentCommit }}</p>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <flux:modal.trigger name="pull-latest">
            <flux:button variant="primary" :disabled="$hasRunning">{{ __('Pull latest changes') }}</flux:button>
        </flux:modal.trigger>
        <x-confirm-modal name="pull-latest" :heading="__('Pull the latest changes from :branch and deploy them?', ['branch' => $currentBranch])" :text="__('The site goes into maintenance mode while this runs.')" :confirm-label="__('Deploy')" confirm-wire="pullLatest" variant="primary" />
        @if ($hasRunning)
            <flux:badge color="amber">{{ __('A deployment is running…') }}</flux:badge>
        @endif
    </div>

    <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
        <div class="border-b border-neutral-100 px-4 py-3 dark:border-neutral-800">
            <flux:heading size="lg">{{ __('Roll back') }}</flux:heading>
            <flux:text class="mt-1 text-xs">{{ __('Reverts the code only. Database migrations are not automatically reversed — review schema compatibility before rolling back past a migration.') }}</flux:text>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($recentCommits as $commit)
                    <tr wire:key="commit-{{ $commit['sha'] }}">
                        <td class="px-4 py-2 font-mono text-xs">{{ substr($commit['sha'], 0, 7) }}</td>
                        <td class="px-4 py-2">{{ $commit['message'] }}</td>
                        <td class="px-4 py-2 text-neutral-500">{{ \Illuminate\Support\Carbon::parse($commit['date'])->diffForHumans() }}</td>
                        <td class="px-4 py-2 text-right">
                            @if ($commit['sha'] === $currentCommit)
                                <flux:badge size="sm">{{ __('Current') }}</flux:badge>
                            @else
                                <flux:modal.trigger name="rollback-{{ $commit['sha'] }}">
                                    <flux:button size="sm" variant="danger" :disabled="$hasRunning">{{ __('Roll back here') }}</flux:button>
                                </flux:modal.trigger>
                                <x-confirm-modal name="rollback-{{ $commit['sha'] }}" :heading="__('Roll back the code to this commit?')" :text="__('The site goes into maintenance mode while this runs.')" :confirm-label="__('Roll back')" confirm-wire="rollback('{{ $commit['sha'] }}')" />
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-4 text-neutral-500">{{ __('No git history available.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
        <div class="border-b border-neutral-100 px-4 py-3 dark:border-neutral-800">
            <flux:heading size="lg">{{ __('History') }}</flux:heading>
        </div>
        <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
            @forelse ($deployments as $deployment)
                <details wire:key="deploy-{{ $deployment->uuid }}" class="px-4 py-3">
                    <summary class="flex cursor-pointer items-center justify-between gap-3">
                        <span class="flex items-center gap-2">
                            <flux:badge size="sm" :color="match ($deployment->status) {
                                \App\Enums\DeploymentStatus::Running => 'amber',
                                \App\Enums\DeploymentStatus::Succeeded => 'green',
                                \App\Enums\DeploymentStatus::Failed => 'red',
                            }">{{ $deployment->status->label() }}</flux:badge>
                            <span class="text-sm font-medium">{{ $deployment->type->label() }}</span>
                            <span class="font-mono text-xs text-neutral-500">{{ substr((string) $deployment->from_commit, 0, 7) }} &rarr; {{ $deployment->to_commit ? substr($deployment->to_commit, 0, 7) : '…' }}</span>
                        </span>
                        <span class="text-xs text-neutral-500">
                            {{ $deployment->triggeredBy?->name ?? __('Unknown') }} &middot; {{ $deployment->started_at?->diffForHumans() }}
                        </span>
                    </summary>
                    <pre class="mt-3 max-h-96 overflow-auto rounded-lg bg-neutral-950 p-3 text-xs text-neutral-200">{{ $deployment->output ?: __('No output yet…') }}</pre>
                </details>
            @empty
                <p class="px-4 py-4 text-neutral-500">{{ __('No deployments yet.') }}</p>
            @endforelse
        </div>
    </div>
</div>
