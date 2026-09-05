<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentCategory;
use App\Enums\DocumentTemplateType;
use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\GeneratedLetter;
use App\Models\User;
use App\Services\Documents\TemplateRenderer;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateLetter
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * @param  array<string, string|int|float|null>  $extras
     */
    public function handle(
        Employee $employee,
        DocumentTemplateType $type,
        string $subject,
        string $body,
        array $extras = [],
        ?DocumentTemplate $template = null,
        ?User $generatedBy = null,
        bool $saveToDocuments = false,
    ): GeneratedLetter {
        return $this->context->run($employee->tenant, function () use ($employee, $type, $subject, $body, $extras, $template, $generatedBy, $saveToDocuments): GeneratedLetter {
            $tenant = $employee->tenant;
            $rendered = $this->renderer->render($subject, $body, $employee, $tenant, $extras);

            return DB::transaction(function () use ($employee, $tenant, $type, $rendered, $template, $generatedBy, $saveToDocuments): GeneratedLetter {
                $letter = GeneratedLetter::query()->create([
                    'document_template_id' => $template?->getKey(),
                    'employee_id' => $employee->getKey(),
                    'type' => $type,
                    'subject' => $rendered['subject'],
                    'body' => $rendered['body'],
                    'context' => ['values' => $rendered['context'], 'missing' => $rendered['missing']],
                    'generated_by' => $generatedBy?->getKey(),
                ]);

                if ($saveToDocuments) {
                    $html = view('documents.letter', [
                        'letter' => $letter, 'tenant' => $tenant, 'employee' => $employee,
                    ])->render();

                    $path = "tenants/{$tenant->uuid}/employees/{$employee->uuid}/letters/{$letter->uuid}.html";
                    Storage::disk('local')->put($path, $html);

                    $document = EmployeeDocument::query()->create([
                        'employee_id' => $employee->getKey(),
                        'category' => DocumentCategory::Other,
                        'title' => $rendered['subject'],
                        'original_filename' => Str::slug($rendered['subject']).'.html',
                        'disk' => 'local',
                        'path' => $path,
                        'mime_type' => 'text/html',
                        'size_bytes' => strlen($html),
                        'is_confidential' => in_array($type, [DocumentTemplateType::WarningLetter, DocumentTemplateType::TerminationLetter], true),
                        'uploaded_by' => $generatedBy?->getKey(),
                    ]);

                    $letter->forceFill(['employee_document_id' => $document->getKey()])->save();
                }

                return $letter;
            });
        });
    }
}
