<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b2e4f">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <title>@yield('title') - {{ config('app.name') }}</title>
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/plugins/boxicons/css/boxicons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/fonts.css') }}" rel="stylesheet">
    <style>
        :root {
            --theme-accent: #1d4e89;
            --theme-accent-2: #f97316;
            --theme-dark: #0b2e4f;
            --theme-ring: rgba(29, 78, 137, .28);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, "Segoe UI", sans-serif;
            color: #172033;
            overflow-x: hidden;
        }

        .bg-aurora {
            position: fixed;
            inset: 0;
            z-index: 0;
            background: linear-gradient(135deg, #061a2e 0%, #0b2e4f 35%, #123a63 55%, #061a2e 100%);
            background-size: 400% 400%;
            animation: gradientShift 18s ease infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .module-wave {
            position: fixed;
            left: 0; right: 0; bottom: 0;
            z-index: 2;
            pointer-events: none;
        }

        .module-wave svg { display: block; width: 100%; height: auto; }
        .module-wave-fill { fill: var(--theme-dark); }

        .module-list {
            background: var(--theme-dark);
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: .5rem 1rem;
            padding: .75rem 4vw 1rem;
        }

        .module-node {
            display: flex;
            align-items: center;
            gap: .4rem;
            color: rgba(255, 255, 255, .85);
            font-size: .72rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
        }

        .module-node i { font-size: 1rem; color: #7fb0e8; }
        .module-node span { overflow: hidden; text-overflow: ellipsis; }

        .login-page {
            position: relative;
            z-index: 3;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 6vw 8rem;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(255, 255, 255, .93);
            backdrop-filter: blur(18px) saturate(1.4);
            border: 1px solid rgba(255, 255, 255, .7);
            border-radius: 18px;
            box-shadow: 0 32px 80px rgba(10, 18, 35, .28), 0 0 0 1px rgba(255, 255, 255, .18) inset;
            overflow: hidden;
        }

        .login-card-accent {
            height: 4px;
            background: linear-gradient(90deg, var(--theme-accent), var(--theme-accent-2) 60%, var(--theme-accent));
        }

        .login-card-body { padding: 2rem 2rem 1.5rem; }
        .brand-area { text-align: center; margin-bottom: 1.25rem; }

        .brand-title {
            font-family: 'Rye', Georgia, serif;
            font-weight: 400;
            font-size: 2.1rem;
            line-height: 1;
            margin: 0;
            white-space: nowrap;
        }

        .brand-title .w3d-w {
            color: #1d4e89;
            text-shadow: 1px 1px 0 #17406f, 2px 2px 0 #123457, 3px 3px 0 #0d2740, 4px 5px 5px rgba(0, 0, 0, .35);
        }

        .brand-title .w3d-n {
            margin-left: 6px;
            color: #f97316;
            text-shadow: 1px 1px 0 #d9620c, 2px 2px 0 #b5500a, 3px 3px 0 #8a3d07, 4px 5px 5px rgba(0, 0, 0, .35);
        }

        .brand-subtitle {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
            color: #64748b;
            margin: .6rem 0 0;
        }

        .form-divider {
            display: flex;
            align-items: center;
            gap: .75rem;
            color: #94a3b8;
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin: 1.25rem 0 1rem;
        }

        .form-divider::before, .form-divider::after { content: ""; flex: 1; height: 1px; background: #e5e7eb; }

        .login-card .form-label { font-size: .84rem; font-weight: 700; color: #334155; }
        .login-card .input-group-text { background: #f1f5f9; border-color: #dfe5ee; color: #64748b; }
        .login-card .form-control { border-color: #dfe5ee; padding-top: .6rem; padding-bottom: .6rem; }
        .login-card .form-control:focus { border-color: var(--theme-accent); box-shadow: 0 0 0 .2rem var(--theme-ring); }
        .password-toggle { border-color: #dfe5ee; background: #fff; color: #64748b; }

        .login-button {
            background: linear-gradient(135deg, var(--theme-accent) 0%, var(--theme-dark) 100%);
            border: none;
            color: #fff;
            font-weight: 800;
            font-size: .95rem;
            padding: .7rem;
            border-radius: .5rem;
            box-shadow: 0 8px 24px var(--theme-ring);
        }

        .login-button:hover { color: #fff; opacity: .95; }
        .login-footer { text-align: center; font-size: .75rem; color: #94a3b8; margin-top: 1.25rem; }

        @media (max-width: 600px) {
            .login-page { padding: 1.5rem 1.25rem 9rem; }
            .module-list { grid-template-columns: repeat(3, 1fr); }
            .module-node span { display: none; }
            .brand-title { font-size: 1.7rem; }
        }
    </style>
    @stack('styles')
</head>
<body>
    @hasSection('background')
        @yield('background')
    @else
        <div class="bg-aurora"></div>
    @endif

    <div class="module-wave">
        <svg viewBox="0 0 1200 60" preserveAspectRatio="none">
            <path class="module-wave-fill" d="M0,30 C150,60 350,0 600,20 C850,40 1050,0 1200,25 L1200,60 L0,60 Z"></path>
        </svg>
        <div class="module-list">
            @foreach (collect(config('planned_modules'))->sortBy('order')->take(6) as $module)
                <div class="module-node">
                    <i class="{{ $module['icon'] }}"></i>
                    <span>{{ $module['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <main class="login-page @yield('page_class')">
        <section class="login-card">
            <div class="login-card-accent"></div>
            <div class="login-card-body">
                <div class="brand-area">
                    <h1 class="brand-title"><span class="w3d-w">{{ config('brand.logo.first') }}</span><span class="w3d-n">{{ config('brand.logo.second') }}</span></h1>
                    <p class="brand-subtitle">{{ config('brand.tagline') }}</p>
                </div>

                @yield('content')

                <div class="login-footer">&copy; {{ date('Y') }} {{ config('app.name') }}</div>
            </div>
        </section>
    </main>

    @stack('scripts')
</body>
</html>
