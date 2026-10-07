{{-- Mise en page commune des documents PDF (CLAUDE.md §7, règle 6), relevée sur le bon de sortie officiel de
     l'Université : ministère et République du Mali en tête, logo, encadré de l'entité (« RECTORAT »), titre, numéro,
     lieu et date, contenu, signatures soulignées (libellé et nom). Textes réglés dans Paramètres → Documents. DomPDF : CSS en ligne seulement,
     police DejaVu Sans (accents), logo embarqué en data URI. Paramètres : title, number, signatures (list<{label, name}>), stamp?, date?. --}}
@php
    // En-tête, logo et signataires : Paramètres → Documents (App\Support\DocumentSettings).
    $documentSettings = app(\App\Support\DocumentSettings::class);
    $head = $documentSettings->header();
    $logoData = $documentSettings->logoDataUri();
    $docDate = ($date ?? null) ?: now();
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18mm 14mm 16mm 14mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #1f2937; }
        table.official { width: 100%; margin-bottom: 4px; }
        table.official td { vertical-align: top; font-weight: bold; color: #1d3f8f; font-size: 8.5pt; }
        td.ministry { width: 55%; }
        td.republic { text-align: right; }
        .stars { letter-spacing: 2px; margin: 2px 0 4px 40px; }
        .logo { height: 78px; margin-left: 24px; }
        .structure { font-size: 11pt; text-transform: uppercase; margin-left: 30px; }
        .service, .motto { font-weight: normal; color: #374151; }
        .place { margin-top: 14px; font-weight: normal; color: #1f2937; }
        .doc-title { text-align: center; margin: 6px 0 14px; }
        .doc-title .entity { display: inline-block; border: 2px solid #1d3f8f; padding: 2px 12px; font-size: 14pt; font-weight: bold; color: #1d3f8f; }
        .doc-title .title { font-size: 15pt; font-weight: bold; color: #1d3f8f; text-transform: uppercase; margin-top: 6px; }
        .doc-title .number { font-size: 10.5pt; font-weight: bold; }
        table.info { width: 100%; margin-bottom: 10px; }
        table.info td { padding: 2px 4px; }
        table.info .label { color: #6b7280; width: 22%; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.lines th { background: #1d3f8f; color: #fff; font-size: 8.5pt; padding: 5px 4px; text-align: left; text-transform: uppercase; }
        table.lines td { border-bottom: 1px solid #d1d5db; padding: 5px 4px; vertical-align: top; }
        table.lines td.num, table.lines th.num { text-align: right; }
        tr.gap td { background: #fff7ed; }
        /* Quadrillage du carnet officiel (bon de sortie). */
        table.official-grid td { border: 1px solid #1d3f8f; }
        table.official-grid th { border: 1px solid #1d3f8f; }
        .note { margin-top: 10px; padding: 6px 8px; background: #f3f4f6; }
        table.signatures { width: 100%; margin-top: 28px; }
        table.signatures td { width: 33%; text-align: center; vertical-align: top; font-weight: bold; padding-top: 4px; color: #1d3f8f; text-decoration: underline; }
        .sign-space { height: 55px; }
        .sign-name { font-weight: bold; color: #1f2937; text-decoration: none; }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <table class="official">
        <tr>
            <td class="ministry">
                {{ mb_strtoupper($head['ministry']) }}
                <div class="stars">**********</div>
                @if ($logoData)
                    <img src="{{ $logoData }}" alt="" class="logo">
                @else
                    <div class="structure">{{ $head['structure'] }}</div>
                @endif
                <div class="service">{{ $head['service'] }}</div>
            </td>
            <td class="republic">
                {{ mb_strtoupper($head['country']) }}
                <div class="motto">{{ $head['motto'] }}</div>
                <div class="place">{{ $head['city'] }}, le {{ $docDate->locale('fr')->translatedFormat('d F Y') }}</div>
            </td>
        </tr>
    </table>
    <div class="doc-title">
        <span class="entity">{{ mb_strtoupper($head['entity']) }}</span>
        <div class="title">{{ $title }}</div>
        <div class="number">{{ $number }}</div>
        @isset($stamp)
            <div class="stamp">{{ $stamp }}</div>
        @endisset
    </div>

    @yield('body')

    <table class="signatures">
        <tr>
            @foreach ($signatures as $signature)
                <td>{{ $signature['label'] }}<div class="sign-space"></div><div class="sign-name">{{ $signature['name'] }}</div></td>
            @endforeach
        </tr>
    </table>

    <div class="footer">{{ config('brand.name') }} — document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</body>
</html>
