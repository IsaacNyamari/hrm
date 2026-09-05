<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApplicantSource;
use App\Facades\Tenancy;
use App\Models\Applicant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Applicant::class);

        $applicants = Applicant::query()
            ->withCount('applications')
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where(fn ($inner) => $inner->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json(['data' => $applicants->map(fn (Applicant $a): array => $this->transform($a))]);
    }

    public function show(Applicant $applicant): JsonResponse
    {
        $this->authorize('view', $applicant);

        $applicant->load(['applications.vacancy:id,title,slug']);

        return response()->json(['data' => $this->transform($applicant, detailed: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Applicant::class);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('applicants', 'email')->where('tenant_id', Tenancy::id())],
            'phone' => ['nullable', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:150'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'source' => ['sometimes', new Enum(ApplicantSource::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:'.(int) config('recruitment.resume_max_kb', 10240)],
        ]);

        $applicant = Applicant::query()->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'location' => $data['location'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'source' => $data['source'] ?? ApplicantSource::Other->value,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($request->hasFile('resume')) {
            $this->storeResume($request, $applicant);
        }

        return response()->json(['data' => $this->transform($applicant->fresh())], 201);
    }

    public function update(Request $request, Applicant $applicant): JsonResponse
    {
        $this->authorize('update', $applicant);

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:150'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:'.(int) config('recruitment.resume_max_kb', 10240)],
        ]);

        $applicant->fill(Arr::except($data, ['resume']))->save();

        if ($request->hasFile('resume')) {
            $this->storeResume($request, $applicant);
        }

        return response()->json(['data' => $this->transform($applicant->fresh())]);
    }

    public function resume(Applicant $applicant): StreamedResponse
    {
        $this->authorize('view', $applicant);

        abort_if($applicant->resume_path === null, 404);
        $disk = $applicant->resume_disk ?? 'local';
        abort_unless(Storage::disk($disk)->exists($applicant->resume_path), 404);

        return Storage::disk($disk)->download($applicant->resume_path, $applicant->fullName().' CV');
    }

    private function storeResume(Request $request, Applicant $applicant): void
    {
        $tenant = Tenancy::getOrFail();
        $disk = (string) config('recruitment.resume_disk', 'local');
        $path = $request->file('resume')->store("tenants/{$tenant->uuid}/applicants/{$applicant->uuid}", $disk);

        $applicant->forceFill(['resume_disk' => $disk, 'resume_path' => $path])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Applicant $applicant, bool $detailed = false): array
    {
        $base = [
            'uuid' => $applicant->uuid,
            'name' => $applicant->fullName(),
            'email' => $applicant->email,
            'phone' => $applicant->phone,
            'source' => $applicant->source->value,
            'has_resume' => $applicant->resume_path !== null,
            'applications_count' => $applicant->applications_count ?? null,
        ];

        if (! $detailed) {
            return $base;
        }

        return $base + [
            'location' => $applicant->location,
            'linkedin_url' => $applicant->linkedin_url,
            'notes' => $applicant->notes,
            'applications' => $applicant->relationLoaded('applications')
                ? $applicant->applications->map(fn ($a): array => [
                    'uuid' => $a->uuid,
                    'vacancy' => $a->vacancy->title,
                    'stage' => $a->stage,
                    'status' => $a->status->value,
                ])->all()
                : [],
        ];
    }
}
