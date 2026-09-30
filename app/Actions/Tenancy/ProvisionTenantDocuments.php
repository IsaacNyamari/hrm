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
                [
                    DocumentTemplateType::OfferLetter,
                    'Offer of Employment',
                    'Offer of Employment',
                    "Dear {{ employee.first_name }},\n\nWe are pleased to offer you the position of {{ employee.position }} in the {{ employee.department }} department at {{ company.name }}, starting on {{ employee.hire_date }}.\n\nYour gross monthly remuneration will be {{ employee.salary }}, subject to statutory deductions. Your appointment is subject to a probationary period and to the terms of your contract of employment.\n\nPlease sign and return a copy of this letter to confirm your acceptance.\n\nYours sincerely,\n{{ company.name }}",
                ],
                [
                    DocumentTemplateType::WarningLetter,
                    'Written Warning',
                    'Written Warning',
                    "Dear {{ employee.first_name }},\n\nThis letter is a formal written warning issued on {{ date }} following the matter discussed with you. The details of the conduct or performance concern, and the improvement expected, are set out below.\n\n[Describe the incident, the standard expected and the timeframe for improvement.]\n\nFurther breaches may lead to disciplinary action in line with {{ company.name }}'s policies and the Employment Act, 2007. You have the right to be accompanied by a colleague of your choice at any disciplinary hearing.",
                ],
                [
                    DocumentTemplateType::TerminationLetter,
                    'Termination of Employment',
                    'Termination of Employment',
                    "Dear {{ employee.first_name }},\n\nThis letter confirms that your employment as {{ employee.position }} with {{ company.name }} ends on {{ employee.exit_date }}.\n\nYou will be paid all amounts due to you, including salary up to your last day and any accrued untaken leave, less statutory deductions, and you will receive a certificate of service. Please return all company property before your last day.\n\nWe thank you for your service.",
                ],
                [
                    DocumentTemplateType::ExperienceCertificate,
                    'Certificate of Service',
                    'Certificate of Service',
                    "This is to certify that {{ employee.name }} worked for {{ company.name }} as {{ employee.position }} in the {{ employee.department }} department from {{ employee.hire_date }} to {{ employee.exit_date }}.\n\nThis certificate is issued on {{ date }} in accordance with Section 51 of the Employment Act, 2007, at the request of the employee.",
                ],
                [
                    DocumentTemplateType::SalaryCertificate,
                    'Salary Certificate',
                    'Certificate of Earnings',
                    "To whom it may concern,\n\nThis is to certify that {{ employee.name }} (Employee No. {{ employee.number }}) is employed by {{ company.name }} as {{ employee.position }} since {{ employee.hire_date }} and currently earns a gross monthly salary of {{ employee.salary }}.\n\nThis certificate is issued on {{ date }} at the employee's request and carries no liability for {{ company.name }}.",
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
