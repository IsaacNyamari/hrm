<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\GeneratedLetter;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    Storage::fake('local');
    $this->tenant = tenantWithRoles(['slug' => 'ltr-co', 'name' => 'Letter Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);
    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => $this->employeeUser->id, 'first_name' => 'Mary', 'last_name' => 'Kim', 'job_title' => 'Engineer',
    ]);
});

it('generates a letter from a template, substituting employee and company values', function () {
    $template = DocumentTemplate::factory()->forTenant($this->tenant)->create([
        'subject' => 'Employment letter for {{ employee.name }}',
        'body' => '{{ employee.name }} is employed by {{ company.name }} as {{ employee.position }}.',
    ]);

    $response = $this->actingAs($this->hr)
        ->postJson('http://ltr-co.hrm.test/letters', [
            'employee_id' => $this->employee->id,
            'template_uuid' => $template->uuid,
            'save_to_documents' => true,
        ])
        ->assertCreated();

    expect($response->json('data.subject'))->toBe('Employment letter for Mary Kim')
        ->and($response->json('data.body'))->toBe('Mary Kim is employed by Letter Co as Engineer.')
        ->and($response->json('data.saved_document'))->toBeTrue();

    $letter = GeneratedLetter::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();
    $document = EmployeeDocument::query()->find($letter->employee_document_id);
    expect($document)->not->toBeNull();
    Storage::disk('local')->assertExists($document->path);
});

it('generates an ad-hoc letter without a template', function () {
    $this->actingAs($this->hr)
        ->postJson('http://ltr-co.hrm.test/letters', [
            'employee_id' => $this->employee->id,
            'type' => 'salary_increment',
            'subject' => 'Salary review — {{ employee.first_name }}',
            'body' => 'Effective {{ today }}, your new title is {{ new_title }}.',
            'extras' => ['new_title' => 'Senior Engineer'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.subject', 'Salary review — Mary')
        ->assertJsonPath('data.saved_document', false);
});

it('lets the employee view and download their own letter', function () {
    $template = DocumentTemplate::factory()->forTenant($this->tenant)->create();
    $uuid = $this->actingAs($this->hr)->postJson('http://ltr-co.hrm.test/letters', [
        'employee_id' => $this->employee->id, 'template_uuid' => $template->uuid,
    ])->json('data.uuid');

    $this->actingAs($this->employeeUser)->getJson("http://ltr-co.hrm.test/letters/{$uuid}")->assertOk();
    $this->actingAs($this->employeeUser)
        ->get("http://ltr-co.hrm.test/letters/{$uuid}/download")
        ->assertOk()
        ->assertSee('Mary Kim');
});

it('forbids generating a letter without permission', function () {
    $this->actingAs($this->employeeUser)
        ->postJson('http://ltr-co.hrm.test/letters', [
            'employee_id' => $this->employee->id, 'type' => 'custom', 'subject' => 'S', 'body' => 'B',
        ])
        ->assertForbidden();
});

it('does not leak another employee\'s letter', function () {
    $letter = GeneratedLetter::factory()->forEmployee(Employee::factory()->forTenant($this->tenant)->create())->create();

    $this->actingAs($this->employeeUser)
        ->getJson("http://ltr-co.hrm.test/letters/{$letter->uuid}")
        ->assertForbidden();
});
