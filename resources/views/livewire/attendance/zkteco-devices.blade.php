<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="lg">{{ __('Biometric devices') }}</flux:heading>
            <flux:text class="mt-1">{{ __('ZKTeco fingerprint terminals — employees clock in and out at the gate.') }}</flux:text>
        </div>
        <flux:button variant="primary" wire:click="startCreate">{{ __('Register device') }}</flux:button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                <tr>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Serial number') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3">{{ __('Last seen') }}</th>
                    <th class="px-4 py-3">{{ __('Pending commands') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($devices as $row)
                    @php($d = $row['device'])
                    <tr wire:key="zk-{{ $d->serial_number }}">
                        <td class="px-4 py-3 font-medium">{{ $d->name ?? __('Unnamed device') }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $d->serial_number }}</td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="$row['online'] ? 'lime' : 'zinc'">
                                {{ $row['online'] ? __('Online') : __('Offline') }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-neutral-500">{{ $d->last_activity_at?->diffForHumans() ?? __('Never') }}</td>
                        <td class="px-4 py-3 text-neutral-500">{{ $row['pending'] }}</td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <flux:button size="sm" variant="primary" wire:click="manageEmployees('{{ $d->serial_number }}')">{{ __('Employees') }}</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="sendCheck('{{ $d->serial_number }}')">{{ __('Test connection') }}</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="queryUsers('{{ $d->serial_number }}')">{{ __('List enrolled users') }}</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="startRename('{{ $d->serial_number }}')">{{ __('Rename') }}</flux:button>
                            <flux:modal.trigger name="reboot-device-{{ $d->serial_number }}">
                                <flux:button size="sm" variant="ghost">{{ __('Reboot') }}</flux:button>
                            </flux:modal.trigger>
                            <x-confirm-modal
                                name="reboot-device-{{ $d->serial_number }}"
                                :heading="__('Reboot this device?')"
                                :text="__('The terminal will restart and be briefly unavailable.')"
                                :confirm-label="__('Reboot')"
                                confirm-wire="sendReboot('{{ $d->serial_number }}')" />
                            <flux:modal.trigger name="release-device-{{ $d->serial_number }}">
                                <flux:button size="sm" variant="danger">{{ __('Remove') }}</flux:button>
                            </flux:modal.trigger>
                            <x-confirm-modal
                                name="release-device-{{ $d->serial_number }}"
                                :heading="__('Remove this device from your company?')"
                                :text="__('Its attendance history is kept. You can re-register the same serial number later.')"
                                :confirm-label="__('Remove')"
                                confirm-wire="release('{{ $d->serial_number }}')" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-neutral-500">{{ __('No biometric devices registered yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal wire:model.self="showForm" class="max-w-sm">
        <form wire:submit="claim" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Register a device') }}</flux:heading>
            <flux:text size="sm">{{ __('Enter the serial number printed on the back of the terminal. Point the device\'s ADMS server address at this application before or after registering it here — either order works.') }}</flux:text>
            <flux:input wire:model="form.serial_number" :label="__('Serial number')" placeholder="{{ __('e.g. ABKJ12345678') }}" />
            <flux:input wire:model="form.name" :label="__('Name')" placeholder="{{ __('e.g. Main gate') }}" />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Register') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="rename-device" class="max-w-sm">
        <form wire:submit="rename" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Rename device') }}</flux:heading>
            <flux:input wire:model="renameValue" :label="__('Name')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="device-users" class="max-w-2xl">
        <div wire:poll.5s.visible class="flex flex-col gap-4">
            <flux:heading size="lg">
                {{ __('Enrolled users') }} — {{ $viewingUsersDevice?->name ?? $viewingUsersDevice?->serial_number }}
            </flux:heading>
            <flux:text size="sm">
                {{ __('The device answers this on its own schedule, not instantly — this list fills in on its own as replies arrive, no need to refresh the page.') }}
            </flux:text>

            @if ($deviceUsers->isEmpty())
                <flux:text class="rounded-lg border border-dashed border-neutral-300 p-6 text-center dark:border-neutral-700">
                    {{ __('Waiting for the device to reply…') }}
                </flux:text>
            @else
                <div class="max-h-96 overflow-y-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                            <tr>
                                <th class="px-3 py-2">{{ __('PIN') }}</th>
                                <th class="px-3 py-2">{{ __('Name on device') }}</th>
                                <th class="px-3 py-2">{{ __('Employee') }}</th>
                                <th class="px-3 py-2">{{ __('Card') }}</th>
                                <th class="px-3 py-2">{{ __('Privilege') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @foreach ($deviceUsers as $u)
                                <tr wire:key="dev-user-{{ $u->id }}">
                                    <td class="px-3 py-2 font-mono text-xs">{{ $u->pin }}</td>
                                    <td class="px-3 py-2">{{ $u->name ?? __('—') }}</td>
                                    <td class="px-3 py-2 text-neutral-500">{{ $deviceUserEmployeeNames[$u->pin] }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $u->card_number ?? __('—') }}</td>
                                    <td class="px-3 py-2">{{ $u->privilege->value === 14 ? __('Admin') : __('User') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex justify-end">
                <flux:modal.close><flux:button type="button" variant="ghost">{{ __('Close') }}</flux:button></flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="edit-card" class="max-w-sm">
        <form wire:submit="saveCardNumber" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Access card number') }}</flux:heading>
            <flux:text size="sm">{{ __('The number printed on (or encoded in) the employee\'s RFID card. Leave blank to remove card access — they can still verify by PIN + password or fingerprint.') }}</flux:text>
            <flux:input wire:model="cardValue" :label="__('Card number')" placeholder="{{ __('e.g. 0012345678') }}" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="manage-employees" class="max-w-2xl">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Enrol employees on this device') }}</flux:heading>
            <flux:callout icon="information-circle">
                <flux:callout.heading>{{ __('Two steps to get someone recognised at the gate') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('1. Click "Add" below — this pushes their PIN, name, keypad password and card number (if set) to the terminal, so they can already verify with PIN + password or by tapping their card.') }}
                    {{ __('2. For fingerprint, walk them to this terminal once: on its screen use User Mgt → the matching PIN → Edit → Fingerprint, and have them scan a finger. There\'s no way to enrol a fingerprint remotely — it has to happen at the terminal\'s own sensor.') }}
                </flux:callout.text>
            </flux:callout>
            <div class="max-h-96 overflow-y-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                        <tr>
                            <th class="px-3 py-2">{{ __('Employee') }}</th>
                            <th class="px-3 py-2">{{ __('PIN on device') }}</th>
                            <th class="px-3 py-2">{{ __('Keypad password') }}</th>
                            <th class="px-3 py-2">{{ __('Card number') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @foreach ($employees as $emp)
                            <tr wire:key="mgr-emp-{{ $emp->id }}">
                                <td class="px-3 py-2">{{ $emp->first_name }} {{ $emp->last_name }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $emp->id }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $emp->device_password ?? __('not set') }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $emp->device_card_number ?? __('not set') }}</td>
                                <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                                    <flux:button size="sm" variant="primary" wire:click="syncEmployee({{ $emp->id }})">{{ __('Add') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="regeneratePassword({{ $emp->id }})">{{ __('New password') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="startEditCard({{ $emp->id }})">{{ __('Card number') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="manageFingerprints({{ $emp->id }})">{{ __('Fingerprints') }}</flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="unsyncEmployee({{ $emp->id }})">{{ __('Remove') }}</flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end">
                <flux:modal.close><flux:button type="button" variant="ghost">{{ __('Close') }}</flux:button></flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="manage-fingerprints" class="max-w-xl">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">
                {{ __('Fingerprints') }} — {{ $fingerprintsEmployee?->fullName() }}
            </flux:heading>
            <flux:text size="sm">
                {{ __('These templates were captured automatically when this person enrolled directly at a terminal. Push a template to sync it onto your other devices, so they don\'t need to re-enrol at each one.') }}
            </flux:text>

            @if (empty($fingerprints))
                <flux:text class="rounded-lg border border-dashed border-neutral-300 p-6 text-center dark:border-neutral-700">
                    {{ __('No fingerprints on file yet. They need to enrol once at any of your terminals — it will appear here automatically.') }}
                </flux:text>
            @else
                <div class="rounded-lg border border-neutral-200 dark:border-neutral-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                            <tr>
                                <th class="px-3 py-2">{{ __('Finger') }}</th>
                                <th class="px-3 py-2">{{ __('Captured from') }}</th>
                                <th class="px-3 py-2">{{ __('When') }}</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @foreach ($fingerprints as $fid => $data)
                                <tr wire:key="fp-{{ $fid }}">
                                    <td class="px-3 py-2">{{ __('Finger #:n', ['n' => $fid]) }}</td>
                                    <td class="px-3 py-2 text-neutral-500">{{ $deviceNames[$data['device_id']] ?? __('Unknown device') }}</td>
                                    <td class="px-3 py-2 text-neutral-500">{{ \Illuminate\Support\Carbon::parse($data['captured_at'])->diffForHumans() }}</td>
                                    <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                                        <flux:button size="sm" variant="primary" wire:click="pushFingerprintEverywhere({{ $fingerprintsEmployee->id }}, {{ $fid }})">
                                            {{ __('Push to other devices') }}
                                        </flux:button>
                                        <flux:modal.trigger name="delete-fingerprint-{{ $fid }}">
                                            <flux:button size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                                        </flux:modal.trigger>
                                        <x-confirm-modal
                                            name="delete-fingerprint-{{ $fid }}"
                                            :heading="__('Delete this fingerprint?')"
                                            :text="__('It will be removed from every device.')"
                                            :confirm-label="__('Delete')"
                                            confirm-wire="deleteFingerprintEverywhere({{ $fingerprintsEmployee->id }}, {{ $fid }})" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex justify-end">
                <flux:modal.close><flux:button type="button" variant="ghost">{{ __('Close') }}</flux:button></flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
