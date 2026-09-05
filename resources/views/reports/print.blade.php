<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $result['name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1a1a1a; margin: 32px; font-size: 12px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #666; font-size: 11px; margin-bottom: 16px; }
        .summary { display: flex; gap: 24px; margin-bottom: 16px; flex-wrap: wrap; }
        .summary div span { display: block; }
        .summary .k { color: #666; font-size: 10px; text-transform: uppercase; }
        .summary .v { font-size: 14px; font-weight: 600; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; }
        tbody tr:nth-child(even) { background: #fafafa; }
        @media print { body { margin: 0; } .noprint { display: none; } }
    </style>
</head>
<body>
    <h1>{{ $result['name'] }}</h1>
    <p class="meta">{{ $result['category'] }} · generated {{ $result['generated_at'] }}
        @if (! empty($result['filters_applied']))
            · filters: {{ collect($result['filters_applied'])->map(fn ($v, $k) => "{$k}={$v}")->implode(', ') }}
        @endif
    </p>

    @if (! empty($result['summary']))
        <div class="summary">
            @foreach ($result['summary'] as $label => $value)
                <div><span class="k">{{ $label }}</span><span class="v">{{ $value }}</span></div>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>@foreach ($result['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($result['rows'] as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($result['columns']) }}">No data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="noprint" style="margin-top:16px;color:#888">Use your browser's Print to save this as PDF.</p>
</body>
</html>
