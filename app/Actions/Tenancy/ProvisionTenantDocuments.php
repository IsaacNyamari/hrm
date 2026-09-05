<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Enums\DocumentTemplateType;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Tenant;
use App\Support\TenantContext;

/**
 * Seeds a tenant's default document types (expiry-tracked identity docs) and a
 * couple of starter HR letter templates. Idempotent.
 */
class ProvisionTenantDocuments
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Tenant $tenant): void
    {
        $this->context->run($tenant, function () use ($tenant): void {
            $types = [
                ['National ID', 'NATIONAL_ID', false, null],
                ['Passport', 'PASSPORT', true, [60, 30, 7]],
                ['Work Permit', 'WORK_PERMIT', true, [90, 30, 14, 7]],
                ['Practising Licence', 'PRACTISING_LICENCE', true, [60, 30, 7]],
            ];

            foreach ($types as [$name, $code, $expiry, $marks]) {
                DocumentType::query()->updateOrCreate(
                    ['tenant_id' => $tenant->getKey(), 'code' => $code],
                    ['name' => $name, 'requires_expiry' => $expiry, 'notify_before_days' => $marks, 'is_active' => true],
                );
            }

            $templates = [
                [
                    DocumentTemplateType::EmploymentLetter,
                    'Employment Confirmation Letter',
                    'Confirmation of Employment',
                    "This is to confirm that {{ employee.name }} (Employee No. {{ employee.number }}) is employed by {{ company.name }} as {{ employee.position }} in the {{ employee.department }} department.\n\n{{ employee.name }} has been in continuous employment since {{ employee.hire_date }}. Their current gross monthly remuneration is {{ employee.salary }}.\n\nThis letter is issued on {{ date }} at the request of the employee.",
                ],
                [
                    DocumentTemplateType::ConfirmationLetter,
                    'Probation Confirmation Letter',
                    'Confirmation of Appointment',
                    "Dear {{ employee.first_name }},\n\nFollowing the successful completion of your probationary period, we are pleased to confirm your appointment as {{ employee.position }} with {{ company.name }}, effective {{ today }}.\n\nAll other terms of your employment remain unchanged.\n\nCongratulations, and we look forward to your continued contribution.",
                ],
            ];

            foreach ($templates as [$type, $name, $subject, $body]) {
                DocumentTemplate::query()->firstOrCreate(
                    ['tenant_id' => $tenant->getKey(), 'name' => $name],
                    ['type' => $type, 'subject' => $subject, 'body' => $body, 'is_active' => true],
                );
            }
        });
    }
}
