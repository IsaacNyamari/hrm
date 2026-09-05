<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Expense;

class ExpenseClaimsReport extends BaseReport
{
    public function key(): string
    {
        return 'expense-claims';
    }

    public function name(): string
    {
        return 'Expense claims';
    }

    public function category(): string
    {
        return 'Finance';
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return ['expenses.view'];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required?: bool}>
     */
    public function filters(): array
    {
        return [
            ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
            ['key' => 'date_from', 'label' => 'Incurred from', 'type' => 'date'],
            ['key' => 'date_to', 'label' => 'Incurred to', 'type' => 'date'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function columns(): array
    {
        return ['Reference', 'Employee', 'Title', 'Incurred on', 'Status', 'Total'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return iterable<int, array<int, string|int|float|null>>
     */
    public function rows(array $filters): iterable
    {
        $status = $this->str($filters, 'status');
        $from = $this->date($filters, 'date_from');
        $to = $this->date($filters, 'date_to');

        return Expense::query()
            ->with('employee:id,first_name,last_name')
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($from !== null, fn ($q) => $q->whereDate('incurred_on', '>=', $from->toDateString()))
            ->when($to !== null, fn ($q) => $q->whereDate('incurred_on', '<=', $to->toDateString()))
            ->latest('id')
            ->get()
            ->map(fn (Expense $e): array => [
                $e->reference,
                (string) (data_get($e, 'employee') !== null ? $e->employee->fullName() : '#'.$e->employee_id),
                $e->title,
                $e->incurred_on?->toDateString() ?? '',
                $e->status->value,
                $this->money($e->total_cents),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int|float>
     */
    public function summary(array $filters): array
    {
        $status = $this->str($filters, 'status');
        $from = $this->date($filters, 'date_from');
        $to = $this->date($filters, 'date_to');

        $query = Expense::query()
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($from !== null, fn ($q) => $q->whereDate('incurred_on', '>=', $from->toDateString()))
            ->when($to !== null, fn ($q) => $q->whereDate('incurred_on', '<=', $to->toDateString()));

        return [
            'Claims' => (clone $query)->count(),
            'Total value' => $this->money((int) (clone $query)->sum('total_cents')),
            'Awaiting decision' => (clone $query)->where('status', 'submitted')->count(),
        ];
    }
}
