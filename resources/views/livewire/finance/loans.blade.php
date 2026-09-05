<div class="flex flex-col gap-6">
    <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Loans & advances') }}</h1>

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
        <form wire:submit="request" class="grid gap-3 rounded-xl border border-neutral-200 p-4 sm:grid-cols-[12rem_10rem_8rem_auto] sm:items-end dark:border-neutral-800">
            <label class="text-sm">
                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Type') }}</span>
                <select wire:model="type" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                    @foreach ($types as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">
                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Amount') }}</span>
                <input type="number" step="0.01" wire:model="amount" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
            </label>
            <label class="text-sm">
                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Months') }}</span>
                <input type="number" wire:model="installments" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
            </label>
            <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-neutral-900">{{ __('Request') }}</button>
            @error('amount') <p class="text-xs text-red-600 sm:col-span-4">{{ $message }}</p> @enderror
        </form>

        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <h2 class="text-sm font-semibold">{{ __('My loans') }}</h2>
            <table class="mt-2 w-full text-sm">
                <thead class="text-left text-xs uppercase text-neutral-500">
                    <tr><th class="py-1">{{ __('Type') }}</th><th>{{ __('Principal') }}</th><th>{{ __('Installment') }}</th><th>{{ __('Outstanding') }}</th><th>{{ __('Status') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($mine as $loan)
                        <tr class="border-t border-neutral-100 dark:border-neutral-800">
                            <td class="py-1.5">{{ $loan->type->label() }}</td>
                            <td>{{ number_format($loan->principal_cents / 100, 2) }}</td>
                            <td>{{ number_format($loan->installment_cents / 100, 2) }}</td>
                            <td>{{ number_format($loan->outstanding_cents / 100, 2) }}</td>
                            <td><span class="rounded bg-neutral-100 px-2 py-0.5 text-xs dark:bg-neutral-800">{{ $loan->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-2 text-neutral-500">{{ __('No loans yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($pending->isNotEmpty())
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <h2 class="text-sm font-semibold">{{ __('Pending approval') }}</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($pending as $loan)
                        <li class="flex justify-between">
                            <span class="text-neutral-600 dark:text-neutral-400">{{ $loan->employee->fullName() }} — {{ $loan->type->label() }}</span>
                            <span class="font-medium">{{ number_format($loan->principal_cents / 100, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-1 text-xs text-neutral-400">{{ __('Approve via the loans API.') }}</p>
            </div>
        @endif
    @endunless
</div>
