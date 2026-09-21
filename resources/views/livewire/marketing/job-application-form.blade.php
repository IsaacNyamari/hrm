@php
    $field = 'mt-1.5 block w-full rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm text-night shadow-sm transition placeholder:text-zinc-400 focus:border-brand focus:outline-hidden focus:ring-4 focus:ring-brand/10';
    $label = 'block text-sm font-semibold text-night';
@endphp

<div id="apply">
    @if ($trackingCode)
        <div class="rounded-2xl bg-success/10 p-8 text-center" data-test="application-sent">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-success text-white"><flux:icon.check /></span>
            <h3 class="mt-4 text-lg font-bold text-night">{{ __('Application sent!') }}</h3>
            <p class="mt-2 text-sm text-zinc-600">{{ __('Your tracking code is') }}</p>
            <p class="mt-2 inline-block rounded-xl border-2 border-dashed border-brand/40 bg-white px-5 py-2 font-mono text-2xl font-bold tracking-widest text-brand" data-test="tracking-code">{{ $trackingCode }}</p>
            <p class="mx-auto mt-3 max-w-md text-sm text-zinc-600">{{ __('Save this code. We have also emailed it to :email. Use it with your email address to follow your application — no account needed.', ['email' => $email]) }}</p>
            <a href="{{ route('applications.track', ['code' => $trackingCode]) }}" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white transition hover:bg-indigo-700">
                {{ __('Track my application') }} <flux:icon.arrow-right variant="mini" />
            </a>
        </div>
    @else
        <form wire:submit="submit" class="grid gap-5 sm:grid-cols-2">
            <div class="hidden" aria-hidden="true">
                <label>{{ __('Website') }} <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div>
                <label for="job-first" class="{{ $label }}">{{ __('First name') }} <span class="text-danger">*</span></label>
                <input id="job-first" type="text" wire:model="first_name" autocomplete="given-name" class="{{ $field }}" required>
                @error('first_name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="job-last" class="{{ $label }}">{{ __('Last name') }} <span class="text-danger">*</span></label>
                <input id="job-last" type="text" wire:model="last_name" autocomplete="family-name" class="{{ $field }}" required>
                @error('last_name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="job-email" class="{{ $label }}">{{ __('Email') }} <span class="text-danger">*</span></label>
                <input id="job-email" type="email" wire:model="email" autocomplete="email" class="{{ $field }}" required>
                @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="job-phone" class="{{ $label }}">{{ __('Phone') }} <span class="text-danger">*</span></label>
                <input id="job-phone" type="tel" wire:model="phone" autocomplete="tel" placeholder="07XX XXX XXX" class="{{ $field }}" required>
                @error('phone') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="job-location" class="{{ $label }}">{{ __('Where are you based?') }}</label>
                <input id="job-location" type="text" wire:model="location" placeholder="{{ __('e.g. Nairobi') }}" class="{{ $field }}">
                @error('location') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="job-linkedin" class="{{ $label }}">{{ __('LinkedIn profile') }}</label>
                <input id="job-linkedin" type="url" wire:model="linkedin_url" placeholder="https://linkedin.com/in/…" class="{{ $field }}">
                @error('linkedin_url') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label for="job-cv" class="{{ $label }}">{{ __('CV / résumé (PDF or Word)') }} <span class="text-danger">*</span></label>
                <input id="job-cv" type="file" wire:model="resume" accept=".pdf,.doc,.docx"
                    class="mt-1.5 block w-full rounded-xl border border-dashed border-zinc-300 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 file:mr-4 file:rounded-lg file:border-0 file:bg-brand file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
                <p wire:loading wire:target="resume" class="mt-1 text-xs text-zinc-500">{{ __('Uploading…') }}</p>
                @error('resume') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label for="job-cover" class="{{ $label }}">{{ __('Cover letter') }}</label>
                <textarea id="job-cover" wire:model="cover_letter" rows="5" placeholder="{{ __('Tell us why you are a great fit (optional)') }}" class="{{ $field }}"></textarea>
                @error('cover_letter') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <button type="submit" wire:loading.attr="disabled" class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-brand/25 transition hover:bg-indigo-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="submit">{{ __('Submit application') }}</span>
                    <span wire:loading wire:target="submit">{{ __('Sending…') }}</span>
                </button>
                <p class="mt-3 text-center text-xs text-zinc-500">{{ __('You will get a tracking code to follow your application.') }}</p>
            </div>
        </form>
    @endif
</div>
