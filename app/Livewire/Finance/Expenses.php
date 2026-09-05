<?php

declare(strict_types=1);

namespace App\Livewire\Finance;

use App\Enums\ExpenseCategory;
use App\Exceptions\ExpenseException;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Expense;
use App\Services\Expenses\ExpenseService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Expenses extends Component
{
    use BindsTenant;

    public string $title = '';

    public ?string $notice = null;

    public ?string $error = null;

    public function createClaim(ExpenseService $service): void
    {
        $this->reset(['notice', 'error']);
        $this->validate(['title' => ['required', 'string', 'max:150']]);

        $employee = auth()->user()?->employee;
        if ($employee === null) {
            $this->error = 'Your account is not linked to an employee record.';

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

        $this->reset('title');
        $this->notice = 'Draft claim created. Add line items via the API, then submit.';
    }

    public function submitClaim(string $uuid, ExpenseService $service): void
    {
        $this->reset(['notice', 'error']);
        $expense = Expense::query()->where('uuid', $uuid)->first();

        if ($expense === null || Gate::denies('submit', $expense)) {
            $this->error = 'You cannot submit that claim.';

            return;
        }

        try {
            $service->submit($expense, auth()->user());
            $this->notice = 'Claim '.$expense->reference.' submitted.';
        } catch (ExpenseException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(): View
    {
        $user = auth()->user();
        $employee = $user?->employee;

        $mine = $employee !== null
            ? $employee->expenses()->with('items')->latest('id')->get()
            : collect();

        $toReview = ($user?->can('expenses.approve') || $user?->can('expenses.manage'))
            ? Expense::query()->where('status', 'submitted')->with('employee:id,first_name,last_name')->latest('id')->get()
            : collect();

        return view('livewire.finance.expenses', [
            'hasEmployee' => $employee !== null,
            'mine' => $mine,
            'toReview' => $toReview,
            'categories' => ExpenseCategory::cases(),
        ]);
    }
}
