@php
    $isQuote = $kind === 'quote';
    $field = 'mt-1.5 block w-full rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm text-night shadow-sm transition placeholder:text-zinc-400 focus:border-brand focus:outline-hidden focus:ring-4 focus:ring-brand/10';
    $label = 'block text-sm font-semibold text-night';
@endphp

<div>
    @if ($sent)
        <div class="mt-6 rounded-2xl bg-success/10 p-8 text-center" data-test="lead-sent">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-success text-white"><flux:icon.check /></span>
            <h3 class="mt-4 text-lg font-bold text-night">{{ $isQuote ? __('Request received!') : __('Message sent!') }}</h3>
            <p class="mt-2 text-sm text-zinc-600">
                {{ $isQuote ? __('Thank you. We will review your request and email your quote and access link shortly.') : __('Thanks for getting in touch — we will reply by email soon.') }}
            </p>
            <button type="button" wire:click="$set('sent', false)" class="mt-5 text-sm font-semibold text-brand hover:underline">{{ __('Send another') }}</button>
        </div>
    @else
        <form wire:submit="submit" class="mt-6 grid gap-5 sm:grid-cols-2">
            {{-- Honeypot: hidden from people, filled by bots. --}}
            <div class="hidden" aria-hidden="true">
                <label>{{ __('Website') }} <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div>
                <label for="lead-name" class="{{ $label }}">{{ __('Full name') }} <span class="text-danger">*</span></label>
                <input id="lead-name" type="text" wire:model="name" autocomplete="name" class="{{ $field }}" required>
                @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="lead-email" class="{{ $label }}">{{ __('Work email') }} <span class="text-danger">*</span></label>
                <input id="lead-email" type="email" wire:model="email" autocomplete="email" class="{{ $field }}" required>
                @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="lead-phone" class="{{ $label }}">{{ __('Phone') }}</label>
                <input id="lead-phone" type="tel" wire:model="phone" autocomplete="tel" placeholder="07XX XXX XXX" class="{{ $field }}">
                @error('phone') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="lead-company" class="{{ $label }}">{{ __('Organization') }} @if ($isQuote)<span class="text-danger">*</span>@endif</label>
                <input id="lead-company" type="text" wire:model="company" autocomplete="organization" class="{{ $field }}" @required($isQuote)>
                @error('company') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            @if ($isQuote)
                <div>
                    <label for="lead-employees" class="{{ $label }}">{{ __('Number of employees') }} <span class="text-danger">*</span></label>
                    <input id="lead-employees" type="number" min="1" wire:model="employee_count" class="{{ $field }}" required>
                    @error('employee_count') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="lead-plan" class="{{ $label }}">{{ __('Plan of interest') }}</label>
                    <select id="lead-plan" wire:model="plan_interest" class="{{ $field }}">
                        <option value="">{{ __('Not sure yet') }}</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->slug }}">{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="sm:col-span-2">
                <label for="lead-message" class="{{ $label }}">
                    {{ $isQuote ? __('Anything else we should know?') : __('Message') }}
                    @unless ($isQuote)<span class="text-danger">*</span>@endunless
                </label>
                <textarea id="lead-message" wire:model="message" rows="5" class="{{ $field }}"
                          placeholder="{{ $isQuote ? __('Modules you need, number of branches, biometric devices, timelines…') : __('How can we help?') }}" @required(! $isQuote)></textarea>
                @error('message') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4 sm:col-span-2">
                <p class="text-xs text-zinc-500">{{ __('We will never share your details.') }}</p>
                <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand px-6 py-3 text-sm font-bold text-white shadow-lg shadow-brand/25 transition hover:-translate-y-0.5 hover:bg-indigo-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="submit">{{ $isQuote ? __('Request my quote') : __('Send message') }}</span>
                    <span wire:loading wire:target="submit">{{ __('Sending…') }}</span>
                    <flux:icon.paper-airplane variant="mini" wire:loading.remove wire:target="submit" />
                </button>
            </div>
        </form>
    @endif
</div>
