<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    Storage::fake('local');
    $this->tenant = tenantWithRoles(['slug' => 'doc-co']);
    app(TenantContext::class)->set($this->tenant);

    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->employeeUser->id]);
    $this->hr = userForTenant($this->tenant, Role::HrManager); // has employees.documents.view/upload
});

it('lets an employee upload and download their own document', function () {
    $upload = $this->actingAs($this->employeeUser)
        ->postJson('http://doc-co.hrm.test/employee-documents', [
            'file' => UploadedFile::fake()->create('degree.pdf', 200, 'application/pdf'),
            'title' => 'Degree Certificate',
            'category' => 'certificate',
        ])
        ->assertCreated();

    $uuid = $upload->json('data.uuid');
    $document = EmployeeDocument::query()->where('uuid', $uuid)->firstOrFail();
    Storage::disk('local')->assertExists($document->path);

    $this->actingAs($this->employeeUser)
        ->get("http://doc-co.hrm.test/employee-documents/{$uuid}/download")
        ->assertOk()
        ->assertDownload('degree.pdf');
});

it('stops an employee uploading a restricted category', function () {
    $this->actingAs($this->employeeUser)
        ->postJson('http://doc-co.hrm.test/employee-documents', [
            'file' => UploadedFile::fake()->create('warning.pdf', 50),
            'title' => 'Sneaky', 'category' => 'warning',
        ])
        ->assertStatus(422);
});

it('hides confidential HR documents from the employee', function () {
    $confidential = EmployeeDocument::factory()->forEmployee($this->employee)->create([
        'is_confidential' => true, 'title' => 'Disciplinary note', 'category' => 'disciplinary',
    ]);
    EmployeeDocument::factory()->forEmployee($this->employee)->create(['title' => 'My CV', 'is_confidential' => false]);

    $list = $this->actingAs($this->employeeUser)
        ->getJson('http://doc-co.hrm.test/employee-documents')
        ->assertOk();

    expect(collect($list->json('data'))->pluck('title'))->toContain('My CV')->not->toContain('Disciplinary note');

    $this->actingAs($this->employeeUser)
        ->get("http://doc-co.hrm.test/employee-documents/{$confidential->uuid}/download")
        ->assertForbidden();
});

it('lets HR upload a confidential document for an employee', function () {
    $this->actingAs($this->hr)
        ->postJson('http://doc-co.hrm.test/employee-documents', [
            'file' => UploadedFile::fake()->create('contract.pdf', 300),
            'title' => 'Contract 2026', 'category' => 'contract',
            'employee_id' => $this->employee->id, 'is_confidential' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_confidential', true);
});

it('cannot download another tenant\'s document', function () {
    $other = tenantWithRoles(['slug' => 'rival-doc-co']);
    $doc = EmployeeDocument::factory()->forEmployee(Employee::factory()->forTenant($other)->create())->create();

    $this->actingAs($this->hr)
        ->get("http://doc-co.hrm.test/employee-documents/{$doc->uuid}/download")
        ->assertNotFound();
});
