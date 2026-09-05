<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $letter->subject }}</title>
    <style>
        * { font-family: DejaVu Sans, Georgia, serif; font-size: 12px; color: #1a1a1a; line-height: 1.6; }
        .sheet { max-width: 720px; margin: 40px auto; padding: 0 24px; }
        .letterhead { border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 28px; }
        .letterhead h1 { font-size: 18px; margin: 0; }
        .muted { color: #666; font-size: 11px; }
        .date { margin-bottom: 20px; }
        .subject { font-weight: bold; margin: 20px 0; }
        .body { white-space: pre-wrap; }
        .sign { margin-top: 48px; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="letterhead">
            <h1>{{ $tenant->name }}</h1>
            <div class="muted">
                {{ $tenant->legal_name }}
                @if ($tenant->tax_number) &middot; PIN: {{ $tenant->tax_number }} @endif
            </div>
            @php($address = trim(implode(', ', array_filter([$tenant->address_line1, $tenant->city, $tenant->country]))))
            @if ($address)<div class="muted">{{ $address }}</div>@endif
        </div>

        <div class="date">{{ now()->toFormattedDateString() }}</div>

        <div>
            {{ $employee->fullName() }}<br>
            <span class="muted">Employee No: {{ $employee->employee_number }}</span>
        </div>

        <div class="subject">RE: {{ $letter->subject }}</div>

        <div class="body">{{ $letter->body }}</div>

        <div class="sign">
            Yours sincerely,<br><br>
            _____________________________<br>
            <strong>Human Resources</strong><br>
            {{ $tenant->name }}
        </div>
    </div>
</body>
</html>
