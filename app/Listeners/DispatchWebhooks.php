<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\WebhookEvent;
use App\Events\ApplicationReceived;
use App\Events\EmployeeCreated;
use App\Events\EmployeeTerminated;
use App\Events\ExpenseDecided;
use App\Events\LeaveApproved;
use App\Events\LeaveRejected;
use App\Events\PayrollApproved;
use App\Events\PayrollPaid;
use App\Models\Application;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Services\Webhooks\WebhookService;

class DispatchWebhooks
{
    public function __construct(private readonly WebhookService $webhooks) {}

    public function handleEmployeeCreated(EmployeeCreated $event): void
    {
        $this->webhooks->dispatch(WebhookEvent::EmployeeCreated, $this->employeePayload($event->employee));
    }

    public function handleLeaveApproved(LeaveApproved $event): void
    {
        $this->webhooks->dispatch(WebhookEvent::LeaveApproved, $this->leavePayload($event->leaveRequest));
    }

    public function handleLeaveRejected(LeaveRejected $event): void
    {
        $this->webhooks->dispatch(WebhookEvent::LeaveRejected, $this->leavePayload($event->leaveRequest));
    }

    public function handleExpenseDecided(ExpenseDecided $event): void
    {
        $this->webhooks->dispatch(
            $event->approved ? WebhookEvent::ExpenseApproved : WebhookEvent::ExpenseRejected,
            $this->expensePayload($event->expense),
        );
    }

    public function handleEmployeeTerminated(EmployeeTerminated $event): void
    {
        $this->webhooks->dispatch(WebhookEvent::EmployeeTerminated, $this->employeePayload($event->employee) + ['exit_date' => $event->employee->exit_date?->toDateString()]);
    }

    public function handlePayrollApproved(PayrollApproved $event): void
    {
        $this->webhooks->dispatch(WebhookEvent::PayrollApproved, $this->payrollPayload($event->run));
    }

    public function handlePayrollPaid(PayrollPaid $event): void
    {
        $this->webhooks->dispatch(WebhookEvent::PayrollPaid, $this->payrollPayload($event->run));
    }

    public function handleApplicationReceived(ApplicationReceived $event): void
    {
        $this->webhooks->dispatch(WebhookEvent::ApplicationReceived, $this->applicationPayload($event->application));
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(Employee $employee): array
    {
        return [
            'uuid' => $employee->uuid,
            'employee_number' => $employee->employee_number,
            'name' => $employee->fullName(),
            'email' => $employee->email,
            'status' => $employee->status?->value,
            'hire_date' => $employee->hire_date?->toDateString(),
        ];
    }

    /**
     * Totals only; individual pay never leaves through a webhook.
     *
     * @return array<string, mixed>
     */
    private function payrollPayload(PayrollRun $run): array
    {
        $run->loadMissing('period');

        return [
            'uuid' => $run->uuid,
            'period' => $run->period->name,
            'period_start' => $run->period->start_date->toDateString(),
            'period_end' => $run->period->end_date->toDateString(),
            'status' => $run->status->value,
            'employee_count' => $run->employee_count,
            'gross_cents' => $run->gross_cents,
            'net_cents' => $run->net_cents,
            'payment_reference' => $run->payment_reference,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leavePayload(LeaveRequest $request): array
    {
        return [
            'uuid' => $request->uuid,
            'employee_uuid' => $request->employee->uuid,
            'status' => $request->status->value,
            'start_date' => $request->start_date->toDateString(),
            'end_date' => $request->end_date->toDateString(),
            'days' => $request->days,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expensePayload(Expense $expense): array
    {
        return [
            'uuid' => $expense->uuid,
            'reference' => $expense->reference,
            'employee_uuid' => $expense->employee->uuid,
            'status' => $expense->status->value,
            'total_cents' => $expense->total_cents,
            'currency' => $expense->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationPayload(Application $application): array
    {
        $application->loadMissing(['applicant', 'vacancy']);

        return [
            'uuid' => $application->uuid,
            'vacancy' => $application->vacancy->title,
            'applicant_name' => $application->applicant->fullName(),
            'applicant_email' => $application->applicant->email,
            'stage' => $application->stage,
        ];
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            EmployeeCreated::class => 'handleEmployeeCreated',
            LeaveApproved::class => 'handleLeaveApproved',
            LeaveRejected::class => 'handleLeaveRejected',
            ExpenseDecided::class => 'handleExpenseDecided',
            ApplicationReceived::class => 'handleApplicationReceived',
            EmployeeTerminated::class => 'handleEmployeeTerminated',
            PayrollApproved::class => 'handlePayrollApproved',
            PayrollPaid::class => 'handlePayrollPaid',
        ];
    }
}
