@extends('layouts.guest')

@section('title', 'Connexion')

{{-- Formulaire à droite ; diaporama d'images et légendes à gauche (config/brand.php → login_slides). --}}
@section('page_class', 'login-page--right')

@section('background')
    <div class="bg-aurora"></div>

    <div class="bg-slideshow" aria-hidden="true">
        @foreach ($slides as $index => $slide)
            <div class="bg-slide bg-slide--{{ ($index % 5) + 1 }} @if ($index === 0) active @endif"
                 data-index="{{ $index }}"
                 @if ($slide['image']) style="background-image: url('{{ $slide['image'] }}'); background-position: {{ $slide['position'] }};" @endif></div>
        @endforeach
    </div>

    <div class="bg-overlay"></div>

    <div class="bg-caption-wrap">
        @foreach ($slides as $index => $slide)
            <div class="bg-caption @if ($index === 0) active @endif" data-index="{{ $index }}">
                <span class="bg-badge"><i class="{{ $slide['icon'] }}"></i> {{ $slide['badge'] }}</span>
                <h2 class="bg-title">{{ $slide['title'] }}</h2>
                <p class="bg-text">{{ $slide['text'] }}</p>
            </div>
        @endforeach
    </div>

    @if (count($slides) > 1)
        <div class="bg-dots" id="bgDots">
            @foreach ($slides as $index => $slide)
                <button type="button" class="bg-dot @if ($index === 0) active @endif" data-index="{{ $index }}" aria-label="Image {{ $index + 1 }} : {{ $slide['title'] }}"></button>
            @endforeach
        </div>
    @endif
@endsection

@push('styles')
    <style>
        /* Page de connexion : carte à droite, la scène occupe le reste de l'écran. */
        .login-page--right {
            justify-content: flex-end;
            pointer-events: none; /* laisse passer les clics vers les points du diaporama */
        }

        .login-page--right .login-card { pointer-events: auto; }

        .bg-slideshow { position: fixed; inset: 0; z-index: 0; overflow: hidden; }

        .bg-slide {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1.4s ease-in-out;
            transform: scale(1.06);
        }

        /* Repli sans image : dégradés bleus distincts, pour que le défilement reste visible. */
        .bg-slide--1 { background-image: linear-gradient(135deg, #0b2e4f 0%, #1d4e89 100%); }
        .bg-slide--2 { background-image: linear-gradient(135deg, #123a63 0%, #2a6db5 100%); }
        .bg-slide--3 { background-image: linear-gradient(135deg, #0d3a57 0%, #1f7a8c 100%); }
        .bg-slide--4 { background-image: linear-gradient(135deg, #1b2a52 0%, #3b5fa8 100%); }
        .bg-slide--5 { background-image: linear-gradient(135deg, #0b2e4f 0%, #0f766e 100%); }

        .bg-slide.active { opacity: 1; animation: kenburns 9s ease-out forwards; }

        @keyframes kenburns {
            from { transform: scale(1.06); }
            to { transform: scale(1.16); }
        }

        .bg-overlay {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                linear-gradient(90deg, rgba(4, 16, 30, .70) 0%, rgba(4, 16, 30, .28) 45%, rgba(4, 16, 30, .80) 100%),
                linear-gradient(180deg, rgba(4, 16, 30, .14) 0%, rgba(4, 16, 30, .30) 65%, rgba(4, 16, 30, .88) 100%);
        }

        .bg-caption-wrap {
            position: fixed;
            inset: 0;
            z-index: 1;
            display: flex;
            align-items: center;
            padding: 0 6vw;
            pointer-events: none;
        }

        .bg-caption { display: none; max-width: 560px; color: #fff; }
        .bg-caption.active { display: block; animation: captionIn .8s ease; }

        @keyframes captionIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .bg-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .25);
            color: #fff;
            font-size: .78rem;
            font-weight: 700;
            padding: .3rem .8rem;
            border-radius: 999px;
            margin-bottom: 1rem;
        }

        .bg-title { color: #fff; font-size: clamp(1.4rem, 2.4vw, 2.2rem); font-weight: 800; margin: 0 0 .6rem; text-shadow: 0 2px 14px rgba(0, 0, 0, .45); }
        .bg-text { color: rgba(255, 255, 255, .88); font-size: clamp(.85rem, 1vw, 1.05rem); color: rgba(255, 255, 255, .85); margin: 0; text-shadow: 0 1px 8px rgba(0, 0, 0, .45); }

        .bg-dots { position: fixed; left: 6vw; bottom: 90px; z-index: 3; display: flex; gap: .5rem; pointer-events: auto; }

        .bg-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .35);
            border: none;
            padding: 0;
            cursor: pointer;
        }

        .bg-dot:hover { background: rgba(255, 255, 255, .7); }
        .bg-dot.active { background: #fff; width: 22px; border-radius: 4px; transition: all .3s; }

        @media (max-width: 900px) {
            .bg-caption-wrap, .bg-dots { display: none; }
        }

        @media (max-width: 600px) {
            .login-page--right { justify-content: center; }
        }

        @media (prefers-reduced-motion: reduce) {
            .bg-slide.active, .bg-caption.active, .bg-aurora { animation: none; }
        }
    </style>
@endpush

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger py-2">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="form-divider"><span>Connexion</span></div>

    <form method="POST" action="{{ route('login.store') }}" id="loginForm">
        @csrf

        <div class="mb-3">
            <label for="phone" class="form-label">Numéro de téléphone</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bx bx-phone"></i></span>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                       class="form-control @error('phone') is-invalid @enderror"
                       inputmode="tel" autocomplete="username" placeholder="70 00 00 00" autofocus required>
            </div>
            <div class="form-text">Indicatif +223 ajouté automatiquement pour un numéro de 8 chiffres.</div>
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">Mot de passe</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bx bx-lock-alt"></i></span>
                <input type="password" id="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       autocomplete="current-password" required>
                <button type="button" class="btn password-toggle" id="togglePassword" aria-label="Afficher le mot de passe">
                    <i class="bx bx-show"></i>
                </button>
            </div>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn login-button" id="loginButton">
                <i class="bx bx-log-in-circle"></i> Connexion
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('loginButton');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Connexion en cours…';
        });

        document.getElementById('togglePassword').addEventListener('click', function () {
            const input = document.getElementById('password');
            const icon = this.querySelector('i');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            icon.classList.toggle('bx-show', !isPassword);
            icon.classList.toggle('bx-hide', isPassword);
        });

        // Diaporama : une image toutes les 6 secondes, ou au clic sur un point.
        (function () {
            const slides = document.querySelectorAll('.bg-slide');
            const captions = document.querySelectorAll('.bg-caption');
            const dots = document.querySelectorAll('.bg-dot');
            if (slides.length < 2) return;

            let current = 0;
            let timer = null;

            function activate(index) {
                [slides, captions, dots].forEach((list) => {
                    if (list[current]) list[current].classList.remove('active');
                    if (list[index]) list[index].classList.add('active');
                });
                current = index;
            }

            function start() {
                timer = setInterval(() => activate((current + 1) % slides.length), 6000);
            }

            dots.forEach((dot) => dot.addEventListener('click', function () {
                clearInterval(timer);
                activate(parseInt(this.dataset.index, 10));
                start();
            }));

            start();
        })();
    </script>
@endpush
