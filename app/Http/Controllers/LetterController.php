<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\GenerateLetter;
use App\Enums\DocumentTemplateType;
use App\Facades\Tenancy;
use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\GeneratedLetter;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class LetterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GeneratedLetter::class);

        $user = $request->user();
        $canViewAll = $user?->can('employees.documents.view') === true;
        $ownEmployeeId = $user?->employee?->getKey();

        $letters = GeneratedLetter::query()
            ->with('employee:id,first_name,last_name')
            ->when(! $canViewAll, fn ($q) => $q->where('employee_id', $ownEmployeeId))
            ->when($request->filled('employee_id') && $canViewAll, fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->latest('id')
            ->get();

        return response()->json(['data' => $letters->map(fn (GeneratedLetter $l): array => [
            'uuid' => $l->uuid,
            'type' => $l->type->value,
            'subject' => $l->subject,
            'employee' => $l->employee->fullName(),
            'created_at' => $l->created_at?->toIso8601String(),
        ])]);
    }

    public function store(Request $request, GenerateLetter $action): JsonResponse
    {
        $this->authorize('create', GeneratedLetter::class);

        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', Tenancy::id())],
            'template_uuid' => ['nullable', Rule::exists('document_templates', 'uuid')->where('tenant_id', Tenancy::id())],
            'type' => ['required_without:template_uuid', new Enum(DocumentTemplateType::class)],
            'subject' => ['required_without:template_uuid', 'string', 'max:200'],
            'body' => ['required_without:template_uuid', 'string', 'max:20000'],
            'extras' => ['sometimes', 'array'],
            'save_to_documents' => ['boolean'],
        ]);

        $employee = Employee::query()->findOrFail((int) $data['employee_id']);

        $template = null;
        if (isset($data['template_uuid'])) {
            $template = DocumentTemplate::query()->where('uuid', $data['template_uuid'])->firstOrFail();
        }

        if ($template instanceof DocumentTemplate) {
            $type = $template->type;
            $subject = $template->subject;
            $body = $template->body;
        } else {
            $type = DocumentTemplateType::from((string) $data['type']);
            $subject = (string) $data['subject'];
            $body = (string) $data['body'];
        }

        $letter = $action->handle(
            employee: $employee,
            type: $type,
            subject: $subject,
            body: $body,
            extras: $data['extras'] ?? [],
            template: $template,
            generatedBy: $request->user(),
            saveToDocuments: (bool) ($data['save_to_documents'] ?? false),
        );

        return response()->json(['data' => [
            'uuid' => $letter->uuid,
            'subject' => $letter->subject,
            'body' => $letter->body,
            'saved_document' => $letter->employee_document_id !== null,
        ]], 201);
    }

    public function show(GeneratedLetter $letter): JsonResponse
    {
        $this->authorize('view', $letter);

        return response()->json(['data' => [
            'uuid' => $letter->uuid,
            'type' => $letter->type->value,
            'subject' => $letter->subject,
            'body' => $letter->body,
        ]]);
    }

    public function download(GeneratedLetter $letter, ViewFactory $view): Response
    {
        $this->authorize('view', $letter);

        $letter->load('employee');

        $html = $view->make('documents.letter', [
            'letter' => $letter,
            'employee' => $letter->employee,
            'tenant' => Tenancy::getOrFail(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'inline; filename="letter-'.$letter->uuid.'.html"',
        ]);
    }
}
