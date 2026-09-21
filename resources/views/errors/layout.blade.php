{{--
    Self-contained error layout: inline CSS/SVG only. No Vite, Livewire, Flux,
    database or auth calls, so it still renders when the application itself is
    failing (500) or in maintenance (503).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · @yield('title') — {{ config('app.name', 'HRM') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root { --brand: #423a8e; --cyan: #00cccd; --night: #1f1d36; --ink: #3c4c5c; --muted: #6b7089; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: var(--night); color: #fff; -webkit-font-smoothing: antialiased;
            display: flex; flex-direction: column; min-height: 100vh; overflow-x: hidden; position: relative;
        }
        .glow { position: fixed; border-radius: 9999px; filter: blur(90px); pointer-events: none; }
        .glow.a { width: 34rem; height: 34rem; background: rgba(66, 58, 142, .75); top: -12rem; left: -10rem; }
        .glow.b { width: 28rem; height: 28rem; background: rgba(0, 204, 205, .22); bottom: -10rem; right: -8rem; }
        .dots { position: fixed; inset: 0; pointer-events: none;
            background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,.07) 1px, transparent 0); background-size: 28px 28px; }
        header, main, footer { position: relative; z-index: 1; }
        header { padding: 1.5rem; max-width: 72rem; width: 100%; margin: 0 auto; }
        .brand { display: inline-flex; align-items: center; gap: .6rem; color: #fff; text-decoration: none; font-weight: 800; letter-spacing: -.01em; }
        .brand .mark { width: 2.25rem; height: 2.25rem; border-radius: .6rem; display: grid; place-items: center;
            background: linear-gradient(135deg, var(--brand), var(--cyan)); }
        main { flex: 1; display: grid; place-items: center; padding: 1rem 1.5rem 4rem; }
        .card { width: 100%; max-width: 34rem; text-align: center; }
        .code { font-size: clamp(5rem, 18vw, 9rem); font-weight: 800; line-height: 1; letter-spacing: -.05em;
            background: linear-gradient(90deg, #fff 10%, var(--cyan)); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .icon { width: 3.5rem; height: 3.5rem; margin: 0 auto 1.25rem; border-radius: 1rem; display: grid; place-items: center;
            background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); color: var(--cyan); }
        h1 { margin: 1rem 0 .75rem; font-size: clamp(1.5rem, 4vw, 2rem); font-weight: 800; letter-spacing: -.02em; }
        p { margin: 0 auto; color: #c9ccdc; line-height: 1.65; max-width: 28rem; }
        .actions { margin-top: 2rem; display: flex; flex-wrap: wrap; gap: .75rem; justify-content: center; }
        .btn { display: inline-flex; align-items: center; gap: .5rem; padding: .8rem 1.4rem; border-radius: .8rem; font-size: .9rem;
            font-weight: 700; text-decoration: none; border: 0; cursor: pointer; font-family: inherit; transition: transform .15s, background .15s; }
        .btn:hover { transform: translateY(-1px); }
        .btn.primary { background: var(--cyan); color: var(--night); box-shadow: 0 10px 25px -8px rgba(0,204,205,.5); }
        .btn.primary:hover { background: #4de0e1; }
        .btn.ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.2); }
        .btn.ghost:hover { background: rgba(255,255,255,.08); }
        .hint { margin-top: 2rem; font-size: .8rem; color: var(--muted); }
        footer { text-align: center; padding: 1.5rem; font-size: .8rem; color: var(--muted); }
        footer a { color: #c9ccdc; text-decoration: none; margin: 0 .5rem; }
        footer a:hover { color: var(--cyan); }
    </style>
</head>
<body>
    <div class="glow a"></div>
    <div class="glow b"></div>
    <div class="dots"></div>

    <header>
        <a class="brand" href="{{ url('/') }}">
            <span class="mark">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" width="24" height="24" fill="#fff" aria-hidden="true">
                    <rect x="5" y="5" width="7.5" height="30" rx="3.75"/><rect x="27.5" y="5" width="7.5" height="30" rx="3.75"/>
                    <circle cx="20" cy="12.5" r="4.5"/><path d="M11 24.5c3-5.6 15-5.6 18 0v6.2c-3-4.4-15-4.4-18 0z"/>
                </svg>
            </span>
            {{ config('app.name', 'HRM') }}
        </a>
    </header>

    <main>
        <div class="card">
            <div class="icon">@yield('icon')</div>
            <div class="code">@yield('code')</div>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>

            <div class="actions">
                @section('actions')
                    <a class="btn primary" href="{{ url('/') }}">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.7 2.3a1 1 0 0 0-1.4 0l-7 7A1 1 0 0 0 3 11h1v6a1 1 0 0 0 1 1h3v-5h4v5h3a1 1 0 0 0 1-1v-6h1a1 1 0 0 0 .7-1.7l-7-7Z"/></svg>
                        {{ __('Go to homepage') }}
                    </a>
                    <button type="button" class="btn ghost" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')">
                        {{ __('Go back') }}
                    </button>
                @show
            </div>

            @hasSection('hint')
                <p class="hint">@yield('hint')</p>
            @endif
        </div>
    </main>

    <footer>
        &copy; {{ date('Y') }} {{ config('app.name', 'HRM') }}
        · <a href="{{ url('/docs') }}">{{ __('Docs') }}</a>
        · <a href="{{ url('/contact') }}">{{ __('Contact') }}</a>
    </footer>
</body>
</html>
