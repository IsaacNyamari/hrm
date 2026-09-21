@php
    /** @var \App\Models\Invoice $invoice */
    $tenant = $invoice->tenant;
    $money = fn (int $cents): string => $invoice->currency.' '.number_format($cents / 100, 2);
    $paidPayments = $invoice->payments->where('status', \App\Enums\PaymentStatus::Succeeded);
    $address = array_filter([
        $tenant?->address_line1,
        $tenant?->address_line2,
        trim(implode(' ', array_filter([$tenant?->city, $tenant?->state, $tenant?->postal_code]))),
        $tenant?->country,
    ]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Invoice') }} {{ $invoice->number }}</title>
    <style>
        @page { margin: 40px 45px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 26px; margin: 0; color: #1f4e79; letter-spacing: 1px; }
        .muted { color: #6b7280; }
        .small { font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .right { text-align: right; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-open { background: #fef3c7; color: #92400e; }
        .status-other { background: #e5e7eb; color: #374151; }
        .section { margin-top: 28px; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; margin-bottom: 4px; }
        .items th { background: #1f4e79; color: #fff; text-align: left; padding: 8px; font-size: 10px; }
        .items th.right { text-align: right; }
        .items td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .totals { width: 45%; margin-left: 55%; margin-top: 12px; }
        .totals td { padding: 5px 8px; }
        .totals .grand td { border-top: 2px solid #1f4e79; font-weight: bold; font-size: 13px; }
        .footer { position: fixed; bottom: -20px; left: 0; right: 0; text-align: center; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>{{ __('INVOICE') }}</h1>
                <div class="muted" style="margin-top: 4px;">{{ config('app.name') }}</div>
            </td>
            <td class="right">
                <div style="font-size: 14px; font-weight: bold;">{{ $invoice->number }}</div>
                <div style="margin-top: 6px;">
                    <span class="status {{ $invoice->isPaid() ? 'status-paid' : ($invoice->status === \App\Enums\InvoiceStatus::Open ? 'status-open' : 'status-other') }}">
                        {{ $invoice->status->label() }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <table class="section header">
        <tr>
            <td style="width: 55%;">
                <div class="label">{{ __('Billed to') }}</div>
                <div style="font-weight: bold; font-size: 12px;">{{ $tenant?->legal_name ?: $tenant?->name }}</div>
                @if ($tenant?->legal_name && $tenant->legal_name !== $tenant->name)
                    <div>{{ $tenant->name }}</div>
                @endif
                @foreach ($address as $line)
                    <div>{{ $line }}</div>
                @endforeach
                @if ($tenant?->email)
                    <div>{{ $tenant->email }}</div>
                @endif
                @if ($tenant?->tax_number)
                    <div>{{ __('Tax number') }}: {{ $tenant->tax_number }}</div>
                @endif
            </td>
            <td class="right">
                <table>
                    <tr><td class="muted right">{{ __('Issued') }}</td><td class="right">{{ $invoice->created_at?->format('d M Y') }}</td></tr>
                    <tr><td class="muted right">{{ __('Due') }}</td><td class="right">{{ $invoice->due_at?->format('d M Y') ?? '—' }}</td></tr>
                    @if ($invoice->period_start && $invoice->period_end)
                        <tr><td class="muted right">{{ __('Service period') }}</td><td class="right">{{ $invoice->period_start->format('d M Y') }} – {{ $invoice->period_end->format('d M Y') }}</td></tr>
                    @endif
                    @if ($invoice->subscription?->plan)
                        <tr><td class="muted right">{{ __('Plan') }}</td><td class="right">{{ $invoice->subscription->plan->name }}</td></tr>
                    @endif
                    @if ($invoice->paid_at)
                        <tr><td class="muted right">{{ __('Paid on') }}</td><td class="right">{{ $invoice->paid_at->format('d M Y') }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="section items">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="right" style="width: 12%;">{{ __('Qty') }}</th>
                <th class="right" style="width: 20%;">{{ __('Unit price') }}</th>
                <th class="right" style="width: 20%;">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ $money($item->unit_amount_cents) }}</td>
                    <td class="right">{{ $money($item->amount_cents) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">{{ __('No line items.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="muted">{{ __('Subtotal') }}</td><td class="right">{{ $money($invoice->subtotal_cents) }}</td></tr>
        <tr><td class="muted">{{ __('Tax') }}</td><td class="right">{{ $money($invoice->tax_cents) }}</td></tr>
        <tr class="grand"><td>{{ __('Total') }}</td><td class="right">{{ $money($invoice->total_cents) }}</td></tr>
        <tr><td class="muted">{{ __('Amount paid') }}</td><td class="right">{{ $money($invoice->amount_paid_cents) }}</td></tr>
        <tr><td style="font-weight: bold;">{{ __('Balance due') }}</td><td class="right" style="font-weight: bold;">{{ $money($invoice->outstandingCents()) }}</td></tr>
    </table>

    @if ($paidPayments->isNotEmpty())
        <div class="section">
            <div class="label">{{ __('Payments received') }}</div>
            <table class="items">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Method') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th class="right">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paidPayments as $payment)
                        <tr>
                            <td>{{ $payment->paid_at?->format('d M Y') ?? '—' }}</td>
                            <td>{{ $payment->provider->label() }}</td>
                            <td>{{ $payment->provider_reference ?? $payment->reference }}</td>
                            <td class="right">{{ $money($payment->amount_cents) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">
        {{ config('app.name') }} · {{ __('Invoice') }} {{ $invoice->number }} · {{ __('Generated') }} {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
