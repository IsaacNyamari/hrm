<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    Storage::fake('local');
    $this->tenant = tenantWithRoles(['slug' => 'dt-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create();
});

it('creates a document type', function () {
    $this->actingAs($this->hr)
        ->postJson('http://dt-co.hrm.test/document-types', [
            'name' => 'Fire Safety Certificate', 'code' => 'FIRE_CERT',
            'requires_expiry' => true, 'notify_before_days' => [45, 15],
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'FIRE_CERT')
        ->assertJsonPath('data.notify_before_days', [45, 15]);
});

it('attaches a document type when uploading a document', function () {
    $type = DocumentType::factory()->forTenant($this->tenant)->create(['code' => 'PERMIT']);

    $upload = $this->actingAs($this->hr)
        ->postJson('http://dt-co.hrm.test/employee-documents', [
            'file' => UploadedFile::fake()->create('permit.pdf', 100),
            'title' => 'Permit', 'category' => 'other',
            'document_type_uuid' => $type->uuid,
            'employee_id' => $this->employee->id,
            'expiry_date' => now()->addYear()->toDateString(),
        ])
        ->assertCreated();

    $document = EmployeeDocument::query()->where('uuid', $upload->json('data.uuid'))->firstOrFail();
    expect($document->document_type_id)->toBe($type->id);
});

it('forbids creating a document type without permission', function () {
    $viewer = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($viewer)
        ->postJson('http://dt-co.hrm.test/document-types', ['name' => 'X', 'code' => 'X'])
        ->assertForbidden();
});
