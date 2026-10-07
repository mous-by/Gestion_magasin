<!doctype html>
<html lang="fr" class="semi-bleu">
<head>
    @include('partials.head')
    @stack('styles')
</head>
<body>
    <div class="wrapper">
        @include('partials.sidebar')
        @include('partials.navbar')

        <div class="page-wrapper">
            <div class="page-content">
                @yield('content')
            </div>

            <div class="overlay toggle-icon"></div>
            <a href="javascript:;" class="back-to-top"><i class='bx bxs-up-arrow-alt'></i></a>

            @include('partials.footer')
            @include('partials.theme-customizer')
        </div>
    </div>

    @include('partials.foot')
    @stack('scripts')

    <script>
        @if (session('status'))
            window.addEventListener('DOMContentLoaded', () => Swal.fire({
                icon: 'success',
                text: @json(session('status')),
                timer: 2500,
                showConfirmButton: false,
            }));
        @endif
        @if (session('error'))
            window.addEventListener('DOMContentLoaded', () => Swal.fire({
                icon: 'error', text: @json(session('error')),
            }));
        @endif
    </script>
</body>
</html>
