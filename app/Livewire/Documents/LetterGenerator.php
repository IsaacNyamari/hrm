<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Actions\Documents\GenerateLetter;
use App\Enums\DocumentTemplateType;
use App\Livewire\Concerns\BindsTenant;
use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\GeneratedLetter;
use App\Services\Documents\TemplateRenderer;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class LetterGenerator extends Component
{
    use BindsTenant;

    public ?string $employeeUuid = null;

    public ?string $templateUuid = null;

    public string $previewSubject = '';

    public string $previewBody = '';

    public bool $saveToDocuments = true;

    // Template management
    public bool $showTemplateForm = false;

    public ?string $editingTemplateUuid = null;

    /** @var array<string, mixed> */
    public array $templateForm = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', DocumentTemplate::class);
    }

    public function updated(string $property, TemplateRenderer $renderer): void
    {
        if (in_array($property, ['employeeUuid', 'templateUuid'], true)) {
            $this->buildPreview($renderer);
        }
    }

    private function buildPreview(TemplateRenderer $renderer): void
    {
        $this->previewSubject = $this->previewBody = '';

        if ($this->employeeUuid === null || $this->templateUuid === null) {
            return;
        }

        $employee = Employee::query()->where('uuid', $this->employeeUuid)->first();
        $template = DocumentTemplate::query()->where('uuid', $this->templateUuid)->first();

        if ($employee === null || $template === null) {
            return;
        }

        $rendered = $renderer->render($template->subject, $template->body, $employee, $employee->tenant);
        $this->previewSubject = $rendered['subject'];
        $this->previewBody = $rendered['body'];
    }

    public function generate(GenerateLetter $action): void
    {
        $employee = Employee::query()->where('uuid', $this->employeeUuid)->first();
        $template = DocumentTemplate::query()->where('uuid', $this->templateUuid)->first();

        if ($employee === null || $template === null) {
            Flux::toast(variant: 'danger', text: __('Choose an employee and a template.'));

            return;
        }

        Gate::authorize('create', GeneratedLetter::class);

        $action->handle(
            employee: $employee,
            type: $template->type,
            subject: $template->subject,
            body: $template->body,
            template: $template,
            generatedBy: auth()->user(),
            saveToDocuments: $this->saveToDocuments,
        );

        Flux::toast(variant: 'success', text: $this->saveToDocuments
            ? __('Letter generated and filed to the employee\'s documents.')
            : __('Letter generated.'));
    }

    // --- Template management ---------------------------------------------

    public function startCreateTemplate(): void
    {
        Gate::authorize('create', DocumentTemplate::class);
        $this->resetErrorBag();
        $this->editingTemplateUuid = null;
        $this->templateForm = $this->blankTemplateForm();
        $this->showTemplateForm = true;
    }

    public function startEditTemplate(string $uuid): void
    {
        $template = DocumentTemplate::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $template);

        $this->resetErrorBag();
        $this->editingTemplateUuid = $template->uuid;
        $this->templateForm = [
            'name' => $template->name,
            'type' => $template->type->value,
            'subject' => $template->subject,
            'body' => $template->body,
            'is_active' => $template->is_active,
        ];
        $this->showTemplateForm = true;
    }

    public function saveTemplate(): void
    {
        $data = $this->validate([
            'templateForm.name' => ['required', 'string', 'max:120'],
            'templateForm.type' => ['required', 'string'],
            'templateForm.subject' => ['required', 'string', 'max:200'],
            'templateForm.body' => ['required', 'string', 'max:20000'],
            'templateForm.is_active' => ['boolean'],
        ])['templateForm'];

        $attributes = [
            'name' => $data['name'],
            'type' => DocumentTemplateType::from($data['type']),
            'subject' => $data['subject'],
            'body' => $data['body'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($this->editingTemplateUuid === null) {
            Gate::authorize('create', DocumentTemplate::class);
            DocumentTemplate::query()->create($attributes);
            Flux::toast(variant: 'success', text: __('Template added.'));
        } else {
            $template = DocumentTemplate::query()->where('uuid', $this->editingTemplateUuid)->firstOrFail();
            Gate::authorize('update', $template);
            $template->update($attributes);
            Flux::toast(variant: 'success', text: __('Template updated.'));
        }

        $this->templateForm = $this->blankTemplateForm();
        $this->editingTemplateUuid = null;
        $this->showTemplateForm = false;
    }

    public function removeTemplate(string $uuid): void
    {
        $template = DocumentTemplate::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('delete', $template);

        $template->delete();
        Flux::modal('delete-template-'.$uuid)->close();
        Flux::toast(variant: 'success', text: __('Template deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function blankTemplateForm(): array
    {
        return ['name' => '', 'type' => 'custom', 'subject' => '', 'body' => '', 'is_active' => true];
    }

    public function render(): View
    {
        $user = auth()->user();
        $canManageTemplates = $user?->can('employees.documents.upload');

        return view('livewire.documents.letter-generator', [
            'employees' => Employee::query()->active()->orderBy('first_name')->get(['uuid', 'first_name', 'last_name']),
            'templates' => DocumentTemplate::query()->where('is_active', true)->orderBy('name')->get(['uuid', 'name']),
            'canManageTemplates' => $canManageTemplates,
            'allTemplates' => $canManageTemplates ? DocumentTemplate::query()->orderBy('name')->get() : collect(),
            'templateTypes' => DocumentTemplateType::cases(),
        ]);
    }
}
