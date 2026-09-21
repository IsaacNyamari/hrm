<?php

declare(strict_types=1);

namespace App\Livewire\Finance;

use App\Enums\ExpenseCategory;
use App\Exceptions\ExpenseException;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Expense;
use App\Services\Expenses\ExpenseService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Expenses extends Component
{
    use BindsTenant;

    public bool $showForm = false;

    public string $title = '';

    public function startCreate(): void
    {
        $this->reset('title');
        $this->showForm = true;
    }

    public function createClaim(ExpenseService $service): void
    {
        $this->validate(['title' => ['required', 'string', 'max:150']]);

        $employee = auth()->user()?->employee;
        if ($employee === null) {
            Flux::toast(variant: 'danger', text: __('Your account is not linked to an employee record.'));

            return;
        }

        Expense::query()->create([
            'reference' => $service->nextReference(),
            'employee_id' => $employee->getKey(),
            'title' => $this->title,
            'currency' => $employee->tenant->currency,
            'incurred_on' => now()->toDateString(),
            'submitted_by' => auth()->id(),
        ]);

        $this->showForm = false;
        Flux::toast(variant: 'success', text: __('Draft claim created. Add line items via the API, then submit.'));
    }

    public function submitClaim(string $uuid, ExpenseService $service): void
    {
        $expense = Expense::query()->where('uuid', $uuid)->first();

        if ($expense === null || Gate::denies('submit', $expense)) {
            Flux::toast(variant: 'danger', text: __('You cannot submit that claim.'));

            return;
        }

        try {
            $service->submit($expense, auth()->user());
            Flux::toast(variant: 'success', text: __('Claim :ref submitted.', ['ref' => $expense->reference]));
        } catch (ExpenseException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function approveClaim(string $uuid, ExpenseService $service): void
    {
        $this->decideClaim($uuid, true, $service);
    }

    public function rejectClaim(string $uuid, ExpenseService $service): void
    {
        $this->decideClaim($uuid, false, $service);
    }

    public function reimburseClaim(string $uuid, ExpenseService $service): void
    {
        $expense = Expense::query()->where('uuid', $uuid)->first();

        if ($expense === null || Gate::denies('reimburse', $expense)) {
            Flux::toast(variant: 'danger', text: __('You cannot mark that claim reimbursed.'));

            return;
        }

        try {
            $service->markReimbursed($expense, null, auth()->user());
            Flux::toast(variant: 'success', text: __('Claim :ref marked reimbursed.', ['ref' => $expense->reference]));
        } catch (ExpenseException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function deleteClaim(string $uuid): void
    {
        $expense = Expense::query()->where('uuid', $uuid)->first();

        if ($expense === null || Gate::denies('delete', $expense)) {
            Flux::toast(variant: 'danger', text: __('You cannot delete that claim.'));

            return;
        }

        $expense->delete();
        Flux::modal('delete-claim-'.$uuid)->close();
        Flux::toast(variant: 'success', text: __('Claim deleted.'));
    }

    private function decideClaim(string $uuid, bool $approved, ExpenseService $service): void
    {
        $expense = Expense::query()->where('uuid', $uuid)->first();

        if ($expense === null || Gate::denies('decide', $expense)) {
            Flux::toast(variant: 'danger', text: __('You cannot act on that claim.'));

            return;
        }

        try {
            $service->decide($expense, auth()->user(), $approved);
            Flux::toast(variant: 'success', text: __('Claim :ref :outcome.', ['ref' => $expense->reference, 'outcome' => $approved ? __('approved') : __('rejected')]));
        } catch (ExpenseException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function render(): View
    {
        $user = auth()->user();
        $employee = $user?->employee;
        $canManage = $user?->can('expenses.manage');

        $mine = $employee !== null
            ? $employee->expenses()->with('items')->latest('id')->get()
            : collect();

        $toReview = ($user?->can('expenses.approve') || $canManage)
            ? Expense::query()->where('status', 'submitted')->with('employee:id,first_name,last_name')->latest('id')->get()
            : collect();

        $toReimburse = $canManage
            ? Expense::query()->where('status', 'approved')->with('employee:id,first_name,last_name')->latest('id')->get()
            : collect();

        return view('livewire.finance.expenses', [
            'hasEmployee' => $employee !== null,
            'mine' => $mine,
            'toReview' => $toReview,
            'toReimburse' => $toReimburse,
            'canManage' => $canManage,
            'categories' => ExpenseCategory::cases(),
        ]);
    }
}
