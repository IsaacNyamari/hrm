<div class="flex flex-col gap-6">
    <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Expense claims') }}</h1>

    @if ($notice)
        <p class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-950 dark:text-green-300">{{ $notice }}</p>
    @endif
    @if ($error)
        <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ $error }}</p>
    @endif

    @unless ($hasEmployee)
        <p class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-neutral-500 dark:border-neutral-700">
            {{ __('Your account is not linked to an employee record.') }}
        </p>
    @else
        <form wire:submit="createClaim" class="flex flex-wrap items-end gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <label class="text-sm">
                <span class="text-neutral-600 dark:text-neutral-400">{{ __('New claim title') }}</span>
                <input type="text" wire:model="title" class="mt-1 w-72 rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
            </label>
            <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-neutral-900">{{ __('Create draft') }}</button>
            @error('title') <p class="w-full text-xs text-red-600">{{ $message }}</p> @enderror
        </form>

        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <h2 class="text-sm font-semibold">{{ __('My claims') }}</h2>
            <table class="mt-2 w-full text-sm">
                <thead class="text-left text-xs uppercase text-neutral-500">
                    <tr><th class="py-1">{{ __('Ref') }}</th><th>{{ __('Title') }}</th><th>{{ __('Items') }}</th><th>{{ __('Total') }}</th><th>{{ __('Status') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($mine as $claim)
                        <tr class="border-t border-neutral-100 dark:border-neutral-800">
                            <td class="py-1.5 font-mono text-xs">{{ $claim->reference }}</td>
                            <td>{{ $claim->title }}</td>
                            <td>{{ $claim->items->count() }}</td>
                            <td>{{ number_format($claim->total_cents / 100, 2) }} {{ $claim->currency }}</td>
                            <td><span class="rounded bg-neutral-100 px-2 py-0.5 text-xs dark:bg-neutral-800">{{ $claim->status->label() }}</span></td>
                            <td class="text-right">
                                @if ($claim->status->isEditable())
                                    <button type="button" wire:click="submitClaim('{{ $claim->uuid }}')" class="text-xs text-blue-600 hover:underline">{{ __('Submit') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-2 text-neutral-500">{{ __('No claims yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="mt-2 text-xs text-neutral-400">{{ __('Add line items (with receipts) to a draft claim through the expense-claims API, then submit here.') }}</p>
        </div>

        @if ($toReview->isNotEmpty())
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <h2 class="text-sm font-semibold">{{ __('Awaiting my decision') }}</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($toReview as $claim)
                        <li class="flex justify-between">
                            <span class="text-neutral-600 dark:text-neutral-400">{{ $claim->reference }} — {{ $claim->employee->fullName() }} — {{ $claim->title }}</span>
                            <span class="font-medium">{{ number_format($claim->total_cents / 100, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-1 text-xs text-neutral-400">{{ __('Approve or reject via the expense-claims API.') }}</p>
            </div>
        @endif
    @endunless
</div>
