@php
    $currency = $payslip->currency;
    $money = fn (int $cents) => number_format($cents / 100, 2);
    $num = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    $period = $payslip->run->period;
    $snapshot = (array) $payslip->snapshot;
    $earnings = $payslip->lines->where('type.value', 'earning')->filter(fn ($l) => $l->amount_cents > 0);
    $deductions = $payslip->lines->where('type.value', 'deduction')->filter(fn ($l) => $l->amount_cents > 0);
    $settlement = $snapshot['final_settlement'] ?? null;
    $attendance = $snapshot['attendance'] ?? null;
    $reference = 'PS-'.strtoupper(substr(str_replace('-', '', $payslip->uuid), 0, 10));
    $status = $payslip->status->value;
    [$stampText, $stampColor] = match ($status) {
        'paid' => ['PAID', '#198754'],
        'approved' => ['APPROVED', '#0d6efd'],
        default => ['DRAFT', '#6c757d'],
    };

    $branding = $tenant->branding;
    $logo = null;
    if ($branding?->logo_path) {
        $disk = \Illuminate\Support\Facades\Storage::disk($branding->logo_disk ?? 'public');
        if ($disk->exists($branding->logo_path)) {
            $logo = 'data:'.($disk->mimeType($branding->logo_path) ?: 'image/png').';base64,'.base64_encode($disk->get($branding->logo_path));
        }
    }
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $reference }}</title>
    <style>
        @page { margin: 18px 24px; }
        * { font-family: DejaVu Sans, sans-serif; color: #1f2937; }
        body { font-size: 9.5px; margin: 0; }
        .center { text-align: center; }
        .muted { color: #6b7280; }
        .brand { color: #423a8e; }
        .logo { height: 34px; }
        table.badge { margin: 0 auto; border-collapse: collapse; }
        table.badge td { width: 34px; height: 34px; border-radius: 17px; background: #423a8e; color: #fff; font-weight: bold; font-size: 12px; text-align: center; vertical-align: middle; padding: 0; }
        .org { font-size: 13px; font-weight: bold; margin-top: 4px; }
        .title { font-size: 8.5px; letter-spacing: 2px; text-transform: uppercase; margin-top: 6px; }
        .amount-label { font-size: 8.5px; text-transform: uppercase; letter-spacing: 1px; margin-top: 8px; }
        .amount { font-size: 20px; font-weight: bold; margin: 2px 0 1px; }
        .stamp { display: inline-block; border: 1.5px solid; border-radius: 3px; padding: 2px 8px; font-size: 9px; font-weight: bold; letter-spacing: 2px; margin-top: 4px; }
        .rule { border-top: 1px dashed #c7c9d1; margin: 8px 0; }
        table.rows { width: 100%; border-collapse: collapse; }
        table.rows td { padding: 2.5px 0; vertical-align: top; }
        table.rows td.value { text-align: right; font-weight: bold; }
        table.rows tr.dotted td { border-bottom: 1px dotted #e5e7eb; }
        .section { font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; color: #423a8e; margin: 2px 0 4px; }
        tr.total td { border-top: 1px solid #1f2937; padding-top: 4px; font-weight: bold; }
        .net { background: #f3f2fb; border-radius: 4px; padding: 4px 8px; margin-top: 8px; }
        .net td { font-size: 11px; font-weight: bold; }
        .note { font-size: 8px; margin-top: 6px; line-height: 1.4; }
        .notice { border: 1px solid #dc3545; color: #dc3545; padding: 6px 8px; border-radius: 4px; font-size: 9px; margin-top: 10px; }
        .notice * { color: #dc3545; }
        .footer { font-size: 7.5px; color: #9ca3af; margin-top: 6px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="center">
        @if ($logo)
            <img src="{{ $logo }}" class="logo" alt="">
        @else
            <table class="badge"><tr><td>{{ \Illuminate\Support\Str::of($tenant->name)->substr(0, 2)->upper() }}</td></tr></table>
        @endif
        <div class="org">{{ $tenant->name }}</div>
        @if ($tenant->tax_number)
            <div class="muted">PIN {{ $tenant->tax_number }}</div>
        @endif

        <div class="title muted">{{ $settlement ? 'Final settlement receipt' : 'Payslip receipt' }}</div>

        <div class="amount-label muted">Net pay</div>
        <div class="amount brand">{{ $currency }} {{ $money($payslip->net_pay_cents) }}</div>
        <div class="muted">to {{ $payslip->employee->fullName() }}</div>
        <div class="stamp" style="color: {{ $stampColor }}; border-color: {{ $stampColor }}">{{ $stampText }}</div>
    </div>

    <div class="rule"></div>

    <table class="rows">
        <tr class="dotted"><td class="muted">Reference</td><td class="value">{{ $reference }}</td></tr>
        <tr class="dotted"><td class="muted">Pay period</td><td class="value">{{ $period->name }}</td></tr>
        <tr class="dotted"><td class="muted">Pay date</td><td class="value">{{ $period->pay_date->format('d M Y') }}</td></tr>
        <tr class="dotted"><td class="muted">Employee no.</td><td class="value">{{ $payslip->employee_number }}</td></tr>
        @if (! empty($snapshot['designation']) || ! empty($snapshot['department']))
            <tr class="dotted"><td class="muted">Position</td><td class="value">{{ collect([$snapshot['designation'] ?? null, $snapshot['department'] ?? null])->filter()->implode(' · ') }}</td></tr>
        @endif
        @if (! empty($snapshot['kra_pin']))
            <tr class="dotted"><td class="muted">KRA PIN</td><td class="value">{{ $snapshot['kra_pin'] }}</td></tr>
        @endif
        @if (! empty($snapshot['bank']))
            <tr class="dotted"><td class="muted">Paid to account</td><td class="value">{{ $snapshot['bank'] }}</td></tr>
        @endif
        @if (is_array($attendance) && ($attendance['working_days'] ?? 0) > 0)
            <tr class="dotted" data-test="payslip-attendance">
                <td class="muted">Attendance</td>
                <td class="value">
                    {{ $num($attendance['payable_days'] ?? 0) }} of {{ $num($attendance['cycle_working_days'] ?? $attendance['working_days']) }} days paid
                    ({{ match ($snapshot['salary_basis'] ?? 'fixed') { 'attendance' => 'attendance-based pay', 'hourly' => 'hourly pay', default => 'fixed salary' } }})<br>
                    <span class="muted" style="font-weight: normal">Hours: {{ $num($attendance['worked_hours'] ?? 0) }} · Overtime: {{ $num(($attendance['overtime_hours'] ?? 0) + ($attendance['rest_day_hours'] ?? 0)) }} h</span>
                </td>
            </tr>
        @endif
    </table>

    @if (is_array($settlement))
        <div class="notice" data-test="payslip-final-settlement">
            <strong>Final settlement</strong> — {{ $settlement['reason'] ?? '' }}, exit date {{ \Carbon\CarbonImmutable::parse($settlement['exit_date'])->format('d M Y') }}.
            @if (($settlement['leave_days_encashed'] ?? 0) > 0)
                Includes {{ $num($settlement['leave_days_encashed']) }} days of untaken leave.
            @endif
        </div>
    @endif

    <div class="rule"></div>

    <div class="section">Earnings</div>
    <table class="rows">
        @forelse ($earnings as $line)
            <tr><td>{{ $line->name }}</td><td class="value" style="font-weight: normal">{{ $money($line->amount_cents) }}</td></tr>
        @empty
            <tr><td class="muted">No earnings this period</td><td class="value">0.00</td></tr>
        @endforelse
        <tr class="total"><td>Gross pay</td><td class="value">{{ $money($payslip->gross_earnings_cents) }}</td></tr>
    </table>

    <div class="section" style="margin-top: 8px">Deductions</div>
    <table class="rows">
        @forelse ($deductions as $line)
            <tr><td>{{ $line->name }}</td><td class="value" style="font-weight: normal">{{ $money($line->amount_cents) }}</td></tr>
        @empty
            <tr><td class="muted">No deductions</td><td class="value">0.00</td></tr>
        @endforelse
        <tr class="total"><td>Total deductions</td><td class="value">{{ $money($payslip->total_deductions_cents) }}</td></tr>
    </table>

    <div class="net">
        <table class="rows"><tr><td>NET PAY</td><td class="value">{{ $currency }} {{ $money($payslip->net_pay_cents) }}</td></tr></table>
    </div>

    <div class="note muted">
        Taxable income {{ $currency }} {{ $money($payslip->taxable_income_cents) }} · Statutory deductions (PAYE, NSSF, SHIF, Housing Levy) {{ $currency }} {{ $money($payslip->statutory_deductions_cents) }}
    </div>

    <div class="rule"></div>

    <div class="footer center">
        Computer-generated receipt — no signature required. Queries: {{ $tenant->name }} HR.<br>
        Ref {{ $reference }} · Generated {{ now()->format('d M Y, H:i') }}
    </div>
</body>
</html>
