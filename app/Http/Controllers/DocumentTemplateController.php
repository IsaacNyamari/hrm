<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentTemplateType;
use App\Facades\Tenancy;
use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Services\Documents\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class DocumentTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', DocumentTemplate::class);

        return response()->json([
            'data' => DocumentTemplate::query()->orderBy('name')->get()->map(fn (DocumentTemplate $t): array => $this->transform($t)),
        ]);
    }

    public function show(DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorize('view', $documentTemplate);

        return response()->json(['data' => $this->transform($documentTemplate, withBody: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DocumentTemplate::class);

        $template = DocumentTemplate::query()->create($this->validated($request));

        return response()->json(['data' => $this->transform($template->fresh(), withBody: true)], 201);
    }

    public function update(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorize('update', $documentTemplate);

        $documentTemplate->update($this->validated($request, $documentTemplate));

        return response()->json(['data' => $this->transform($documentTemplate->fresh(), withBody: true)]);
    }

    public function destroy(DocumentTemplate $documentTemplate): Response
    {
        $this->authorize('delete', $documentTemplate);
        $documentTemplate->delete();

        return response()->noContent();
    }

    /**
     * Render the template for an employee without persisting anything.
     */
    public function preview(Request $request, DocumentTemplate $documentTemplate, TemplateRenderer $renderer): JsonResponse
    {
        $this->authorize('view', $documentTemplate);

        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', Tenancy::id())],
            'extras' => ['sometimes', 'array'],
        ]);

        $employee = Employee::query()->findOrFail((int) $data['employee_id']);
        $rendered = $renderer->render($documentTemplate->subject, $documentTemplate->body, $employee, Tenancy::getOrFail(), $data['extras'] ?? []);

        return response()->json(['data' => [
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'unresolved_placeholders' => $rendered['missing'],
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?DocumentTemplate $template = null): array
    {
        $required = $template === null ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'type' => [$required, new Enum(DocumentTemplateType::class)],
            'subject' => [$required, 'string', 'max:200'],
            'body' => [$required, 'string', 'max:20000'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(DocumentTemplate $template, bool $withBody = false): array
    {
        $base = [
            'uuid' => $template->uuid,
            'name' => $template->name,
            'type' => $template->type->value,
            'subject' => $template->subject,
            'is_active' => $template->is_active,
        ];

        return $withBody ? $base + ['body' => $template->body] : $base;
    }
}
