<div class="flex flex-col gap-4" wire:key="recruitment-board">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">{{ __('Recruitment') }}</flux:heading>
        <div class="flex items-center gap-2">
            <flux:select wire:model.live="vacancyUuid" class="min-w-[16rem]">
                @foreach ($vacancies as $v)
                    <flux:select.option value="{{ $v->uuid }}">{{ $v->title }} — {{ ucfirst($v->status->value) }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($canManage)
                <flux:button variant="primary" wire:click="startCreateVacancy">{{ __('New vacancy') }}</flux:button>
            @endif
        </div>
    </div>

    @if ($vacancy === null)
        <flux:text class="rounded-xl border border-dashed border-neutral-300 p-8 text-center dark:border-neutral-700">
            {{ __('No vacancies yet.') }} @if ($canManage) {{ __('Create one to get started.') }} @endif
        </flux:text>
    @else
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-neutral-200 p-3 dark:border-neutral-800">
            <div>
                <x-status-badge :status="$vacancy->status" />
                <span class="ml-2 text-sm text-neutral-500">{{ __('Headcount') }}: {{ $vacancy->positions_filled }} / {{ $vacancy->headcount }}</span>
            </div>
            @if ($canManage)
                <div class="flex items-center gap-2">
                    @if ($vacancy->status->value === 'draft')
                        <flux:button size="sm" variant="primary" wire:click="publishVacancy('{{ $vacancy->uuid }}')">{{ __('Publish') }}</flux:button>
                    @endif
                    <flux:button size="sm" variant="ghost" wire:click="startEditVacancy('{{ $vacancy->uuid }}')">{{ __('Edit') }}</flux:button>
                    @unless ($vacancy->status->isTerminal())
                        <flux:modal.trigger name="close-vacancy-{{ $vacancy->uuid }}">
                            <flux:button size="sm" variant="danger">{{ __('Close') }}</flux:button>
                        </flux:modal.trigger>
                        <x-confirm-modal
                            name="close-vacancy-{{ $vacancy->uuid }}"
                            :heading="__('Close this vacancy?')"
                            :confirm-label="__('Close vacancy')"
                            confirm-wire="closeVacancy('{{ $vacancy->uuid }}')" />
                    @endunless
                    <flux:button size="sm" variant="primary" wire:click="startAddCandidate">{{ __('Add candidate') }}</flux:button>
                </div>
            @endif
        </div>

        <div class="grid gap-4 overflow-x-auto md:grid-cols-2 xl:grid-cols-5">
            @foreach ($columns as $stage => $apps)
                <div class="min-w-[220px] rounded-xl border border-neutral-200 p-3 dark:border-neutral-800">
                    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        {{ Str::headline($stage) }} <span class="text-neutral-400">({{ $apps->count() }})</span>
                    </h2>
                    <ul class="space-y-2">
                        @forelse ($apps as $app)
                            <li wire:key="app-{{ $app->uuid }}" class="rounded-lg border border-neutral-200 p-2 text-sm dark:border-neutral-700">
                                <p class="font-medium text-neutral-800 dark:text-neutral-200">{{ $app->applicant->fullName() }}</p>
                                <p class="text-xs text-neutral-500">{{ $app->status->value }}</p>
                                @if ($app->status->isOpen())
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach ($vacancy->stages() as $target)
                                            @if ($target !== $stage)
                                                <button type="button"
                                                    wire:click="moveStage('{{ $app->uuid }}', '{{ $target }}')"
                                                    class="rounded bg-neutral-100 px-1.5 py-0.5 text-[11px] text-neutral-600 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300">
                                                    → {{ Str::headline($target) }}
                                                </button>
                                            @endif
                                        @endforeach
                                    </div>
                                    @if ($canManage)
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <button type="button" wire:click="startScheduleInterview('{{ $app->uuid }}')"
                                                class="rounded bg-blue-50 px-1.5 py-0.5 text-[11px] text-blue-700 hover:bg-blue-100 dark:bg-blue-950 dark:text-blue-300">
                                                {{ __('Schedule interview') }}
                                            </button>
                                            <button type="button" wire:click="startMakeOffer('{{ $app->uuid }}')"
                                                class="rounded bg-green-50 px-1.5 py-0.5 text-[11px] text-green-700 hover:bg-green-100 dark:bg-green-950 dark:text-green-300">
                                                {{ __('Make offer') }}
                                            </button>
                                            <flux:modal.trigger name="reject-app-{{ $app->uuid }}">
                                                <button type="button" class="rounded bg-red-50 px-1.5 py-0.5 text-[11px] text-red-700 hover:bg-red-100 dark:bg-red-950 dark:text-red-300">
                                                    {{ __('Reject') }}
                                                </button>
                                            </flux:modal.trigger>
                                            <x-confirm-modal
                                                name="reject-app-{{ $app->uuid }}"
                                                :heading="__('Reject this candidate?')"
                                                :confirm-label="__('Reject')"
                                                confirm-wire="rejectApplication('{{ $app->uuid }}')" />
                                        </div>
                                    @endif
                                @endif
                            </li>
                        @empty
                            <li class="text-xs text-neutral-400">{{ __('Empty') }}</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
    @endif

    @if ($canManage)
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:heading size="sm">{{ __('My interviews') }}</flux:heading>
                <ul class="mt-2 space-y-2 text-sm">
                    @forelse ($myInterviews as $interview)
                        <li wire:key="iv-{{ $interview->uuid }}" class="flex items-center justify-between">
                            <span class="text-neutral-600 dark:text-neutral-400">
                                {{ $interview->application->applicant->fullName() }} — {{ $interview->scheduled_at->format('D, M j g:ia') }}
                            </span>
                            <flux:button size="sm" variant="primary" wire:click="startFeedback('{{ $interview->uuid }}')">{{ __('Add feedback') }}</flux:button>
                        </li>
                    @empty
                        <li class="text-neutral-500">{{ __('No interviews scheduled for you.') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:heading size="sm">{{ __('Offers') }}</flux:heading>
                <ul class="mt-2 space-y-3 text-sm">
                    @forelse ($offersToSend as $offer)
                        <li wire:key="offer-{{ $offer->uuid }}" class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-neutral-600 dark:text-neutral-400">
                                {{ $offer->application->applicant->fullName() }} — {{ $offer->job_title }}
                                <span class="ml-1 rounded-full bg-neutral-100 px-2 py-0.5 text-xs dark:bg-neutral-800">{{ $offer->status->label() }}</span>
                            </span>
                            <span class="flex gap-1">
                                @if ($offer->status->value === 'draft')
                                    <flux:button size="sm" variant="primary" wire:click="sendOffer('{{ $offer->uuid }}')">{{ __('Send') }}</flux:button>
                                @elseif ($offer->status->value === 'sent')
                                    <flux:button size="sm" variant="ghost" wire:click="recordOfferResponse('{{ $offer->uuid }}', true)">{{ __('Accepted') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="recordOfferResponse('{{ $offer->uuid }}', false)">{{ __('Declined') }}</flux:button>
                                @else
                                    <flux:button size="sm" variant="primary" icon="user-plus" wire:click="hire('{{ $offer->uuid }}')"
                                        wire:confirm="{{ __('Create the employee record and staff account, and email the welcome link?') }}">{{ __('Hire & create account') }}</flux:button>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="text-neutral-500">{{ __('No open offers.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif

    <flux:modal wire:model.self="showVacancyForm" class="max-w-lg">
        <form wire:submit="saveVacancy" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingVacancyUuid ? __('Edit vacancy') : __('New vacancy') }}</flux:heading>
            <flux:input wire:model="vacancyForm.title" :label="__('Title')" />
            <div class="grid grid-cols-2 gap-3">
                <flux:select wire:model="vacancyForm.department_id" :label="__('Department')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($departments as $d)
                        <flux:select.option value="{{ $d->id }}">{{ $d->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="vacancyForm.branch_id" :label="__('Branch')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($branches as $b)
                        <flux:select.option value="{{ $b->id }}">{{ $b->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="number" wire:model="vacancyForm.headcount" :label="__('Headcount')" />
                <flux:input wire:model="vacancyForm.location" :label="__('Location')" />
                <flux:input type="date" wire:model="vacancyForm.closes_on" :label="__('Closes on')" />
            </div>
            <flux:field variant="inline">
                <flux:checkbox wire:model="vacancyForm.is_remote" />
                <flux:label>{{ __('Remote') }}</flux:label>
            </flux:field>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showVacancyForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingVacancyUuid ? __('Save changes') : __('Create vacancy') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showCandidateForm" class="max-w-sm">
        <form wire:submit="addCandidate" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Add candidate') }}</flux:heading>
            <flux:input wire:model="candidateForm.first_name" :label="__('First name')" />
            <flux:input wire:model="candidateForm.last_name" :label="__('Last name')" />
            <flux:input type="email" wire:model="candidateForm.email" :label="__('Email')" />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCandidateForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="schedule-interview" class="max-w-sm">
        <form wire:submit="scheduleInterview" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Schedule interview') }}</flux:heading>
            <flux:input type="datetime-local" wire:model="interviewForm.scheduled_at" :label="__('When')" />
            <flux:select wire:model="interviewForm.interviewer_id" :label="__('Interviewer')">
                <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                @foreach ($employees as $emp)
                    <flux:select.option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="grid grid-cols-2 gap-3">
                <flux:select wire:model="interviewForm.mode" :label="__('Mode')">
                    <flux:select.option value="video">{{ __('Video') }}</flux:select.option>
                    <flux:select.option value="phone">{{ __('Phone') }}</flux:select.option>
                    <flux:select.option value="onsite">{{ __('Onsite') }}</flux:select.option>
                </flux:select>
                <flux:input type="number" wire:model="interviewForm.duration_minutes" :label="__('Minutes')" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Schedule') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="interview-feedback" class="max-w-sm">
        <form wire:submit="saveFeedback" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Interview feedback') }}</flux:heading>
            <flux:select wire:model="feedbackForm.recommendation" :label="__('Recommendation')">
                <flux:select.option value="strong_yes">{{ __('Strong yes') }}</flux:select.option>
                <flux:select.option value="yes">{{ __('Yes') }}</flux:select.option>
                <flux:select.option value="no">{{ __('No') }}</flux:select.option>
                <flux:select.option value="strong_no">{{ __('Strong no') }}</flux:select.option>
            </flux:select>
            <flux:input type="number" min="1" max="5" wire:model="feedbackForm.rating" :label="__('Rating (1-5)')" />
            <flux:textarea wire:model="feedbackForm.feedback" :label="__('Notes')" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save feedback') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="make-offer" class="max-w-sm">
        <form wire:submit="makeOffer" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Make offer') }}</flux:heading>
            <flux:input type="number" wire:model="offerForm.salary_cents" :label="__('Salary (cents)')" />
            <flux:input type="date" wire:model="offerForm.start_date" :label="__('Start date')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Draft offer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
