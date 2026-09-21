<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Documents\LetterGenerator;
use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Support\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'ltr-dash-co', 'name' => 'Letter Dash Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator); // employees.documents.upload
    Employee::factory()->forTenant($this->tenant)->create();
});

it('creates a letter template from the dashboard', function () {
    $this->actingAs($this->hr);

    Livewire::test(LetterGenerator::class)
        ->call('startCreateTemplate')
        ->set('templateForm.name', 'Reference letter')
        ->set('templateForm.type', 'recommendation_letter')
        ->set('templateForm.subject', 'Reference for {{ employee.name }}')
        ->set('templateForm.body', '{{ employee.name }} is a valued member of {{ company.name }}.')
        ->call('saveTemplate')
        ->assertDispatched('toast-show')
        ->assertSet('showTemplateForm', false);

    expect(DocumentTemplate::query()->where('name', 'Reference letter')->exists())->toBeTrue();
});

it('edits a letter template from the dashboard', function () {
    $template = DocumentTemplate::factory()->forTenant($this->tenant)->create(['name' => 'Old name']);

    $this->actingAs($this->hr);

    Livewire::test(LetterGenerator::class)
        ->call('startEditTemplate', $template->uuid)
        ->assertSet('templateForm.name', 'Old name')
        ->set('templateForm.name', 'New name')
        ->call('saveTemplate')
        ->assertDispatched('toast-show');

    expect($template->fresh()->name)->toBe('New name');
});

it('deletes a letter template from the dashboard', function () {
    $template = DocumentTemplate::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->hr);

    Livewire::test(LetterGenerator::class)->call('removeTemplate', $template->uuid);

    expect(DocumentTemplate::query()->find($template->id))->toBeNull();
});

it('forbids opening the letters dashboard without a documents permission', function () {
    $viewer = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($viewer);

    Livewire::test(LetterGenerator::class)->assertForbidden();
});
