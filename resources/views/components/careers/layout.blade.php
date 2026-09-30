<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description ?? '' }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @stack('structured-data')
    <style>
        :root { color-scheme: light dark; --fg: #18181b; --muted: #52525b; --line: #e4e4e7; --bg: #fff; --accent: #0f766e; }
        @media (prefers-color-scheme: dark) { :root { --fg: #f4f4f5; --muted: #a1a1aa; --line: #3f3f46; --bg: #18181b; --accent: #2dd4bf; } }
        body { margin: 0; font: 16px/1.6 system-ui, sans-serif; color: var(--fg); background: var(--bg); }
        main { max-width: 48rem; margin: 0 auto; padding: 2rem 1rem; }
        a { color: var(--accent); }
        .job { border-bottom: 1px solid var(--line); padding: 1rem 0; }
        .muted { color: var(--muted); font-size: .9rem; }
        label { display: block; margin: .75rem 0 .25rem; font-weight: 600; }
        input, textarea { width: 100%; box-sizing: border-box; padding: .5rem; border: 1px solid var(--line); border-radius: .5rem; background: transparent; color: inherit; font: inherit; }
        button { margin-top: 1rem; padding: .6rem 1.2rem; border: 0; border-radius: .5rem; background: var(--accent); color: #fff; font: inherit; cursor: pointer; }
        .notice { margin-top: 1rem; padding: .75rem; border-radius: .5rem; border: 1px solid var(--line); }
    </style>
</head>
<body>
    <main>
        <p class="muted"><a href="{{ route('careers.index') }}">{{ $company }}</a> · {{ __('Careers') }}</p>
        {{ $slot }}
    </main>
</body>
</html>
