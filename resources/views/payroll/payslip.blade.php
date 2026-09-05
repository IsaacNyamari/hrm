@php
    $currency = $payslip->currency;
    $money = fn (int $cents) => number_format($cents / 100, 2);
    $earnings = $payslip->lines->where('type.value', 'earning');
    $deductions = $payslip->lines->where('type.value', 'deduction');
    $period = $payslip->run->period;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 16px; margin: 0; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { text-align: left; padding: 5px 8px; border-bottom: 1px solid #e5e5e5; }
        th { background: #f5f5f5; text-transform: uppercase; font-size: 9px; letter-spacing: .04em; }
        td.amt, th.amt { text-align: right; }
        .totals td { font-weight: bold; border-top: 2px solid #333; border-bottom: none; }
        .net { background: #111; color: #fff; padding: 10px 12px; margin-top: 14px; font-size: 13px; }
        .grid { width: 100%; }
        .grid td { border: none; padding: 2px 8px 2px 0; vertical-align: top; }
        .cols { width: 100%; }
        .cols td { border: none; vertical-align: top; width: 50%; }
    </style>
</head>
<body>
    <table class="grid">
        <tr>
            <td style="width:60%">
                <h1>{{ $tenant->name }}</h1>
                <div class="muted">{{ $tenant->legal_name }}</div>
                <div class="muted">PIN: {{ $tenant->tax_number }}</div>
            </td>
            <td style="text-align:right">
                <strong>PAYSLIP</strong><br>
                <span class="muted">{{ $period->name }}</span><br>
                <span class="muted">Pay date: {{ $period->pay_date->toFormattedDateString() }}</span>
            </td>
        </tr>
    </table>

    <table class="grid" style="margin-top:12px">
        <tr>
            <td><strong>{{ $payslip->employee->fullName() }}</strong></td>
            <td>Employee No: {{ $payslip->employee_number }}</td>
        </tr>
        <tr>
            <td class="muted">{{ $payslip->snapshot['designation'] ?? '' }} · {{ $payslip->snapshot['department'] ?? '' }}</td>
            <td class="muted">KRA PIN: {{ $payslip->snapshot['kra_pin'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="muted">Bank: {{ $payslip->snapshot['bank'] ?? '—' }}</td>
            <td class="muted">Currency: {{ $currency }}</td>
        </tr>
    </table>

    <table class="cols">
        <tr>
            <td>
                <table>
                    <thead><tr><th>Earnings</th><th class="amt">Amount</th></tr></thead>
                    <tbody>
                        @foreach ($earnings as $line)
                            <tr><td>{{ $line->name }}</td><td class="amt">{{ $money($line->amount_cents) }}</td></tr>
                        @endforeach
                        <tr class="totals"><td>Gross Earnings</td><td class="amt">{{ $money($payslip->gross_earnings_cents) }}</td></tr>
                    </tbody>
                </table>
            </td>
            <td>
                <table>
                    <thead><tr><th>Deductions</th><th class="amt">Amount</th></tr></thead>
                    <tbody>
                        @foreach ($deductions as $line)
                            <tr><td>{{ $line->name }}{{ $line->is_statutory ? ' *' : '' }}</td><td class="amt">{{ $money($line->amount_cents) }}</td></tr>
                        @endforeach
                        <tr class="totals"><td>Total Deductions</td><td class="amt">{{ $money($payslip->total_deductions_cents) }}</td></tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <table class="grid" style="margin-top:8px">
        <tr>
            <td class="muted">Taxable income: {{ $currency }} {{ $money($payslip->taxable_income_cents) }}</td>
            <td class="muted" style="text-align:right">Statutory: {{ $currency }} {{ $money($payslip->statutory_deductions_cents) }}</td>
        </tr>
    </table>

    <div class="net">NET PAY &nbsp; {{ $currency }} {{ $money($payslip->net_pay_cents) }}</div>

    <p class="muted" style="margin-top:16px">* statutory deduction. This is a computer-generated payslip.</p>
</body>
</html>
