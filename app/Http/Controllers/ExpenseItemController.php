<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Facades\Tenancy;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Services\Expenses\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseItemController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    public function store(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('update', $expense);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:200'],
            'category' => ['required', new Enum(ExpenseCategory::class)],
            'spent_on' => ['required', 'date'],
            'amount_cents' => ['required_unless:category,mileage', 'nullable', 'integer', 'min:0'],
            'quantity' => ['required_if:category,mileage', 'nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.(int) config('expenses.receipt_max_kb', 10240)],
        ]);

        $item = $expense->items()->create([
            'tenant_id' => $expense->tenant_id,
            'description' => $data['description'],
            'category' => $data['category'],
            'spent_on' => $data['spent_on'],
            'amount_cents' => (int) ($data['amount_cents'] ?? 0),
            'quantity' => isset($data['quantity']) ? (float) $data['quantity'] : null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($request->hasFile('receipt')) {
            $this->storeReceipt($request, $item);
        }

        $this->service->recalculateTotal($expense);

        return response()->json(['data' => $this->transform($item->fresh())], 201);
    }

    public function update(Request $request, ExpenseItem $expenseItem): JsonResponse
    {
        $this->authorize('update', $expenseItem->expense);

        $data = $request->validate([
            'description' => ['sometimes', 'string', 'max:200'],
            'category' => ['sometimes', new Enum(ExpenseCategory::class)],
            'spent_on' => ['sometimes', 'date'],
            'amount_cents' => ['sometimes', 'integer', 'min:0'],
            'quantity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.(int) config('expenses.receipt_max_kb', 10240)],
        ]);

        $expenseItem->fill(Arr::except($data, ['receipt']))->save();

        if ($request->hasFile('receipt')) {
            $this->storeReceipt($request, $expenseItem);
        }

        $this->service->recalculateTotal($expenseItem->expense);

        return response()->json(['data' => $this->transform($expenseItem->fresh())]);
    }

    public function destroy(ExpenseItem $expenseItem): Response
    {
        $expense = $expenseItem->expense;
        $this->authorize('update', $expense);

        if ($expenseItem->receipt_path !== null) {
            Storage::disk($expenseItem->receipt_disk ?? 'local')->delete($expenseItem->receipt_path);
        }
        $expenseItem->delete();

        $this->service->recalculateTotal($expense);

        return response()->noContent();
    }

    public function receipt(ExpenseItem $expenseItem): StreamedResponse
    {
        $this->authorize('view', $expenseItem->expense);

        abort_if($expenseItem->receipt_path === null, 404);
        $disk = $expenseItem->receipt_disk ?? 'local';
        abort_unless(Storage::disk($disk)->exists($expenseItem->receipt_path), 404);

        return Storage::disk($disk)->download($expenseItem->receipt_path, 'receipt-'.$expenseItem->uuid);
    }

    private function storeReceipt(Request $request, ExpenseItem $item): void
    {
        $tenant = Tenancy::getOrFail();
        $disk = (string) config('expenses.receipt_disk', 'local');
        $path = $request->file('receipt')->store("tenants/{$tenant->uuid}/expenses/{$item->expense_id}", $disk);

        $item->forceFill(['receipt_disk' => $disk, 'receipt_path' => $path])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(ExpenseItem $item): array
    {
        return [
            'uuid' => $item->uuid,
            'description' => $item->description,
            'category' => $item->category->value,
            'spent_on' => $item->spent_on->toDateString(),
            'amount_cents' => $item->amount_cents,
            'quantity' => $item->quantity,
            'unit_rate_cents' => $item->unit_rate_cents,
            'has_receipt' => $item->receipt_path !== null,
        ];
    }
}
