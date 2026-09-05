<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentCategory;
use App\Facades\Tenancy;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EmployeeDocument::class);

        $user = $request->user();
        $canViewAll = $user?->can('employees.documents.view') === true;
        $ownEmployeeId = $user?->employee?->getKey();

        $query = EmployeeDocument::query()->with('employee:id,first_name,last_name');

        if ($request->filled('employee_id') && $canViewAll) {
            $query->where('employee_id', $request->integer('employee_id'));
        } elseif (! $canViewAll) {
            abort_if($ownEmployeeId === null, 403);
            $query->where('employee_id', $ownEmployeeId)->where('is_confidential', false);
        }

        return response()->json([
            'data' => $query->latest('id')->get()->map(fn (EmployeeDocument $d): array => $this->transform($d)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', EmployeeDocument::class);

        $user = $request->user();
        $canUploadForOthers = $user?->can('employees.documents.upload') === true;

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', new Enum(DocumentCategory::class)],
            'employee_id' => ['nullable', 'integer'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:issue_date'],
            'is_confidential' => ['boolean'],
        ]);

        $employee = $this->resolveEmployee($request, $data['employee_id'] ?? null, $canUploadForOthers);

        if (! $canUploadForOthers) {
            abort_unless(
                in_array(DocumentCategory::from($data['category']), DocumentCategory::employeeUploadable(), true),
                422,
                'You cannot upload a document in that category.',
            );
        }

        $tenant = Tenancy::getOrFail();
        $path = $request->file('file')->store("tenants/{$tenant->uuid}/employees/{$employee->uuid}/documents", 'local');

        $document = EmployeeDocument::query()->create([
            'employee_id' => $employee->getKey(),
            'category' => $data['category'],
            'title' => $data['title'],
            'original_filename' => $request->file('file')->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $request->file('file')->getMimeType(),
            'size_bytes' => $request->file('file')->getSize(),
            'issue_date' => $data['issue_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'is_confidential' => $canUploadForOthers ? (bool) ($data['is_confidential'] ?? false) : false,
            'uploaded_by' => $user?->getKey(),
        ]);

        return response()->json(['data' => $this->transform($document)], 201);
    }

    public function download(EmployeeDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_filename);
    }

    public function destroy(EmployeeDocument $document): Response
    {
        $this->authorize('delete', $document);

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        return response()->noContent();
    }

    private function resolveEmployee(Request $request, ?int $employeeId, bool $canUploadForOthers): Employee
    {
        if ($employeeId !== null) {
            abort_unless($canUploadForOthers, 403);

            return Employee::query()->findOrFail($employeeId);
        }

        $employee = $request->user()?->employee;
        abort_if($employee === null, 422, 'Your account is not linked to an employee record.');

        return $employee;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(EmployeeDocument $document): array
    {
        return [
            'uuid' => $document->uuid,
            'title' => $document->title,
            'category' => $document->category->value,
            'filename' => $document->original_filename,
            'size_bytes' => $document->size_bytes,
            'issue_date' => $document->issue_date?->toDateString(),
            'expiry_date' => $document->expiry_date?->toDateString(),
            'is_confidential' => $document->is_confidential,
            'employee' => $document->relationLoaded('employee') ? $document->employee->fullName() : null,
            'uploaded_at' => $document->created_at?->toIso8601String(),
        ];
    }
}
