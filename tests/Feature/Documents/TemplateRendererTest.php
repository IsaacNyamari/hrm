<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Services\Documents\TemplateRenderer;
use App\Support\TenantContext;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['name' => 'Acme Ltd', 'tax_number' => 'P051234567X']);
    app(TenantContext::class)->set($this->tenant);
    $this->renderer = app(TemplateRenderer::class);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'first_name' => 'Jane', 'last_name' => 'Doe', 'employee_number' => 'EMP-000042',
        'job_title' => 'Accountant', 'salary_amount_cents' => 12_000_000, 'salary_currency' => 'KES',
    ]);
});

it('substitutes whitelisted placeholders', function () {
    $result = $this->renderer->render(
        'Letter for {{ employee.name }}',
        '{{ employee.name }} ({{ employee.number }}) works at {{ company.name }} as {{ employee.position }}. Salary: {{ employee.salary }}.',
        $this->employee,
        $this->tenant,
    );

    expect($result['subject'])->toBe('Letter for Jane Doe')
        ->and($result['body'])->toBe('Jane Doe (EMP-000042) works at Acme Ltd as Accountant. Salary: KES 120,000.00.')
        ->and($result['missing'])->toBe([]);
});

it('is tolerant of surrounding whitespace and mixed case keys', function () {
    $result = $this->renderer->render('x', 'Hi {{employee.first_name}} and {{   company.name   }}', $this->employee, $this->tenant);

    expect($result['body'])->toBe('Hi Jane and Acme Ltd');
});

it('reports unknown placeholders and renders them empty', function () {
    $result = $this->renderer->render('x', 'Bonus: {{ bonus_amount }} — {{ employee.secret }}', $this->employee, $this->tenant);

    expect($result['body'])->toBe('Bonus:  — ')
        ->and($result['missing'])->toContain('bonus_amount', 'employee.secret');
});

it('accepts extra values supplied at generation time', function () {
    $result = $this->renderer->render('x', 'New title: {{ new_position }}', $this->employee, $this->tenant, ['new_position' => 'Senior Accountant']);

    expect($result['body'])->toBe('New title: Senior Accountant')
        ->and($result['missing'])->toBe([]);
});

it('does not execute Blade or PHP in the template body', function () {
    $result = $this->renderer->render(
        'x',
        "{{ 7 * 6 }} @php echo 'x'; @endphp <?php echo 'y'; ?> {!! 'z' !!}",
        $this->employee,
        $this->tenant,
    );

    // `{{ 7 * 6 }}` has a space so it is not a valid [a-z0-9_.] placeholder -> left verbatim
    expect($result['body'])->toContain('{{ 7 * 6 }}')
        ->and($result['body'])->toContain("@php echo 'x'; @endphp")
        ->and($result['body'])->toContain("<?php echo 'y'; ?>")
        ->and($result['body'])->not->toContain('42');
});
