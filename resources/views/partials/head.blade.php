<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#123a63">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

<link href="{{ asset('assets/plugins/simplebar/css/simplebar.css') }}" rel="stylesheet" />
<link href="{{ asset('assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css') }}" rel="stylesheet" />
<link href="{{ asset('assets/plugins/metismenu/css/metisMenu.min.css') }}" rel="stylesheet" />
<link href="{{ asset('assets/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />

<link href="{{ asset('assets/css/pace.min.css') }}" rel="stylesheet" />
<script src="{{ asset('assets/js/pace.min.js') }}"></script>

<link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}" rel="stylesheet" />
<link href="{{ asset('assets/plugins/boxicons/css/boxicons.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/fonts.css') }}" rel="stylesheet">
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script src="{{ asset('assets/plugins/sweetalert2/sweetalert2.all.min.js') }}"></script>

<link href="{{ asset('assets/plugins/select2/css/select2.min.css') }}" rel="stylesheet" />
<link href="{{ asset('assets/plugins/select2/css/select2-bootstrap4.css') }}" rel="stylesheet" />

<link rel="stylesheet" href="{{ asset('assets/css/dark-theme.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/css/semi-dark.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/css/header-colors.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/css/numerise.css') }}?v={{ filemtime(public_path('assets/css/numerise.css')) }}" />

<title>@yield('title', 'Tableau de bord') - {{ config('app.name') }}</title>
