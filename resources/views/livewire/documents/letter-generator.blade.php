<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">{{ __('Generate HR letter') }}</flux:heading>
        @if ($canManageTemplates)
            <flux:button variant="primary" wire:click="startCreateTemplate">{{ __('New template') }}</flux:button>
        @endif
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <flux:select wire:model.live="templateUuid" :label="__('Template')">
            <flux:select.option value="">{{ __('Select…') }}</flux:select.option>
            @foreach ($templates as $t)
                <flux:select.option value="{{ $t->uuid }}">{{ $t->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="employeeUuid" :label="__('Employee')">
            <flux:select.option value="">{{ __('Select…') }}</flux:select.option>
            @foreach ($employees as $e)
                <flux:select.option value="{{ $e->uuid }}">{{ $e->fullName() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($previewBody !== '')
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <p class="text-sm font-semibold">{{ $previewSubject }}</p>
            <p class="mt-2 whitespace-pre-wrap text-sm text-neutral-700 dark:text-neutral-300">{{ $previewBody }}</p>
        </div>

        <flux:field variant="inline">
            <flux:checkbox wire:model="saveToDocuments" />
            <flux:label>{{ __('File a copy to the employee\'s documents') }}</flux:label>
        </flux:field>

        <div>
            <flux:button wire:click="generate" variant="primary">{{ __('Generate letter') }}</flux:button>
        </div>
    @endif

    @if ($canManageTemplates)
        <flux:heading size="sm">{{ __('Letter templates') }}</flux:heading>

        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                    <tr>
                        <th class="px-4 py-3">{{ __('Name') }}</th>
                        <th class="px-4 py-3">{{ __('Type') }}</th>
                        <th class="px-4 py-3">{{ __('Subject') }}</th>
                        <th class="px-4 py-3">{{ __('Active') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @forelse ($allTemplates as $template)
                        <tr wire:key="tpl-{{ $template->uuid }}">
                            <td class="px-4 py-3 font-medium">{{ $template->name }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $template->type->label() }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ Str::limit($template->subject, 40) }}</td>
                            <td class="px-4 py-3">{{ $template->is_active ? __('Yes') : __('No') }}</td>
                            <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                <flux:button size="sm" variant="ghost" wire:click="startEditTemplate('{{ $template->uuid }}')">{{ __('Edit') }}</flux:button>
                                <flux:modal.trigger name="delete-template-{{ $template->uuid }}">
                                    <flux:button size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                                </flux:modal.trigger>
                                <x-confirm-modal
                                    name="delete-template-{{ $template->uuid }}"
                                    :heading="__('Delete :name?', ['name' => $template->name])"
                                    :confirm-label="__('Delete')"
                                    confirm-wire="removeTemplate('{{ $template->uuid }}')" />
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-neutral-500">{{ __('No templates yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <flux:modal wire:model.self="showTemplateForm" class="max-w-2xl">
            <form wire:submit="saveTemplate" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ $editingTemplateUuid ? __('Edit template') : __('New template') }}</flux:heading>
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="templateForm.name" :label="__('Name')" />
                    <flux:select wire:model="templateForm.type" :label="__('Type')">
                        @foreach ($templateTypes as $t)
                            <flux:select.option value="{{ $t->value }}">{{ $t->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:input wire:model="templateForm.subject" :label="__('Subject')" placeholder="{{ __('e.g. Confirmation for') }} @{{ employee.name }}" />
                <flux:textarea wire:model="templateForm.body" :label="__('Body')" rows="8" placeholder="{{ __('Use placeholders like') }} @{{ employee.name }}, @{{ company.name }}…" />
                <flux:field variant="inline">
                    <flux:checkbox wire:model="templateForm.is_active" />
                    <flux:label>{{ __('Active') }}</flux:label>
                </flux:field>
                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showTemplateForm', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ $editingTemplateUuid ? __('Save changes') : __('Create template') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
