<?php

declare(strict_types=1);

namespace App\Livewire\Finance;

use App\Enums\ExpenseCategory;
use App\Exceptions\ExpenseException;
use App\Facades\Tenancy;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Services\Expenses\ExpenseService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class Expenses extends Component
{
    use BindsTenant;
    use WithFileUploads;

    public bool $showForm = false;

    public ?string $itemClaimUuid = null;

    /** @var array{description: string, category: string, spent_on: string, amount: string, quantity: string, notes: string} */
    public array $item = ['description' => '', 'category' => 'travel', 'spent_on' => '', 'amount' => '', 'quantity' => '', 'notes' => ''];

    public $receipt = null;

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
        Flux::toast(variant: 'success', text: __('Draft claim created. Add your line items and receipts, then submit.'));
    }

    public function startAddItem(string $claimUuid): void
    {
        $expense = Expense::query()->where('uuid', $claimUuid)->firstOrFail();
        Gate::authorize('update', $expense);

        $this->resetErrorBag();
        $this->itemClaimUuid = $expense->uuid;
        $this->item = ['description' => '', 'category' => 'travel', 'spent_on' => now()->toDateString(), 'amount' => '', 'quantity' => '', 'notes' => ''];
        $this->receipt = null;
        Flux::modal('expense-item')->show();
    }

    public function saveItem(ExpenseService $service): void
    {
        $expense = Expense::query()->where('uuid', (string) $this->itemClaimUuid)->firstOrFail();
        Gate::authorize('update', $expense);

        $isMileage = $this->item['category'] === ExpenseCategory::Mileage->value;

        $this->validate([
            'item.description' => ['required', 'string', 'max:200'],
            'item.category' => ['required', 'in:'.implode(',', array_column(ExpenseCategory::cases(), 'value'))],
            'item.spent_on' => ['required', 'date'],
            'item.amount' => [$isMileage ? 'nullable' : 'required', 'numeric', 'min:0'],
            'item.quantity' => [$isMileage ? 'required' : 'nullable', 'numeric', 'min:0'],
            'item.notes' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.(int) config('expenses.receipt_max_kb', 10240)],
        ]);

        $item = $expense->items()->create([
            'tenant_id' => $expense->tenant_id,
            'description' => $this->item['description'],
            'category' => $this->item['category'],
            'spent_on' => $this->item['spent_on'],
            'amount_cents' => $isMileage ? 0 : (int) round(((float) $this->item['amount']) * 100),
            'quantity' => $isMileage ? (float) $this->item['quantity'] : null,
            'notes' => $this->item['notes'] !== '' ? $this->item['notes'] : null,
        ]);

        if ($this->receipt !== null) {
            $tenant = Tenancy::getOrFail();
            $disk = (string) config('expenses.receipt_disk', 'local');
            $path = $this->receipt->store("tenants/{$tenant->uuid}/expenses/{$expense->getKey()}", $disk);
            $item->forceFill(['receipt_disk' => $disk, 'receipt_path' => $path])->save();
        }

        $service->recalculateTotal($expense);

        $this->receipt = null;
        $this->itemClaimUuid = null;
        Flux::modal('expense-item')->close();
        Flux::toast(variant: 'success', text: __('Line item added.'));
    }

    public function removeItem(string $itemUuid, ExpenseService $service): void
    {
        $item = ExpenseItem::query()->where('uuid', $itemUuid)->firstOrFail();
        $expense = $item->expense;
        Gate::authorize('update', $expense);

        if ($item->receipt_path !== null) {
            Storage::disk($item->receipt_disk ?? 'local')->delete($item->receipt_path);
        }

        $item->delete();
        $service->recalculateTotal($expense);
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
