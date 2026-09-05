<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Actions\Documents\GenerateLetter;
use App\Livewire\Concerns\BindsTenant;
use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\GeneratedLetter;
use App\Services\Documents\TemplateRenderer;
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

    public ?string $notice = null;

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
        $this->notice = null;

        $employee = Employee::query()->where('uuid', $this->employeeUuid)->first();
        $template = DocumentTemplate::query()->where('uuid', $this->templateUuid)->first();

        if ($employee === null || $template === null) {
            $this->notice = 'Choose an employee and a template.';

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

        $this->notice = 'Letter generated'.($this->saveToDocuments ? ' and filed to the employee\'s documents.' : '.');
    }

    public function render(): View
    {
        return view('livewire.documents.letter-generator', [
            'employees' => Employee::query()->active()->orderBy('first_name')->get(['uuid', 'first_name', 'last_name']),
            'templates' => DocumentTemplate::query()->where('is_active', true)->orderBy('name')->get(['uuid', 'name']),
        ]);
    }
}
