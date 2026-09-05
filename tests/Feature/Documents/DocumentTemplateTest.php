<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'tpl-co', 'name' => 'Template Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator); // has employees.documents.upload
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Ken']);
});

it('ships default templates and document types via provisioning', function () {
    expect(DocumentTemplate::query()->pluck('type')->map->value)->toContain('employment_letter', 'confirmation_letter')
        ->and(DocumentType::query()->pluck('code'))->toContain('PASSPORT', 'WORK_PERMIT');
});

it('creates a template and previews it for an employee', function () {
    $create = $this->actingAs($this->hr)
        ->postJson('http://tpl-co.hrm.test/document-templates', [
            'name' => 'Reference', 'type' => 'recommendation_letter',
            'subject' => 'Reference for {{ employee.name }}',
            'body' => '{{ employee.name }} is a valued member of {{ company.name }}.',
        ])
        ->assertCreated();

    $this->actingAs($this->hr)
        ->postJson("http://tpl-co.hrm.test/document-templates/{$create->json('data.uuid')}/preview", [
            'employee_id' => $this->employee->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.subject', 'Reference for Ken '.$this->employee->last_name)
        ->assertJsonPath('data.unresolved_placeholders', []);
});

it('forbids managing templates without the documents-upload permission', function () {
    $viewer = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($viewer)
        ->postJson('http://tpl-co.hrm.test/document-templates', ['name' => 'X', 'type' => 'custom', 'subject' => 'S', 'body' => 'B'])
        ->assertForbidden();
});

it('cannot preview another tenant\'s template', function () {
    $other = tenantWithRoles(['slug' => 'rival-tpl-co']);
    $template = DocumentTemplate::factory()->forTenant($other)->create();

    $this->actingAs($this->hr)
        ->postJson("http://tpl-co.hrm.test/document-templates/{$template->uuid}/preview", ['employee_id' => $this->employee->id])
        ->assertNotFound();
});
