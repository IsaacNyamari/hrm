<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class DocumentTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', DocumentType::class);

        return response()->json([
            'data' => DocumentType::query()->orderBy('name')->get()->map(fn (DocumentType $t): array => $this->transform($t)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DocumentType::class);

        $type = DocumentType::query()->create($this->validated($request));

        return response()->json(['data' => $this->transform($type->fresh())], 201);
    }

    public function update(Request $request, DocumentType $documentType): JsonResponse
    {
        $this->authorize('update', $documentType);

        $documentType->update($this->validated($request, $documentType));

        return response()->json(['data' => $this->transform($documentType->fresh())]);
    }

    public function destroy(DocumentType $documentType): Response
    {
        $this->authorize('delete', $documentType);
        $documentType->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?DocumentType $type = null): array
    {
        $required = $type === null ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'code' => [
                $required, 'string', 'max:40', 'alpha_dash',
                Rule::unique('document_types', 'code')->where('tenant_id', Tenancy::id())->ignore($type?->id),
            ],
            'requires_expiry' => ['boolean'],
            'notify_before_days' => ['nullable', 'array'],
            'notify_before_days.*' => ['integer', 'between:1,365'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(DocumentType $type): array
    {
        return [
            'uuid' => $type->uuid,
            'name' => $type->name,
            'code' => $type->code,
            'requires_expiry' => $type->requires_expiry,
            'notify_before_days' => $type->reminderMarks(),
            'is_active' => $type->is_active,
        ];
    }
}
