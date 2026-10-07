{{-- Aperçu des paramètres (Paramètres → Documents) : un bon de sortie fictif, marqué « Aperçu ». --}}
@extends('pdf._layout', [
    'title' => 'Bon de sortie',
    'number' => 'N° '.strtr(app(\App\Support\DocumentSettings::class)->numberFormat(), [
        '{prefix}' => app(\App\Support\DocumentSettings::class)->prefix('sortie'), '{year}' => now()->year,
        '{number}' => str_pad('1', app(\App\Support\DocumentSettings::class)->numberPadding(), '0', STR_PAD_LEFT),
    ]),
    'stamp' => 'Aperçu — document fictif',
    'signatures' => app(\App\Support\DocumentSettings::class)->signatures('bon_sortie', [
        'demandeur' => 'Nom du demandeur', 'service' => 'Service bénéficiaire', 'valide_par' => auth()->user()?->name, 'saisi_par' => auth()->user()?->name,
    ]),
])

@section('body')
    <table class="info">
        <tr><td class="label">Demandeur :</td><td><strong>Nom du demandeur</strong></td><td class="label">Date :</td><td><strong>{{ now()->format('d/m/Y') }}</strong></td></tr>
    </table>
    <table class="lines official-grid">
        <thead><tr><th style="width: 5%">N°</th><th>Désignations</th><th style="width: 9%">Unité</th><th class="num" style="width: 11%">Quantités</th><th style="width: 30%">Observations</th></tr></thead>
        <tbody>
            <tr><td>01</td><td>Article d'exemple</td><td>U</td><td class="num"><strong>1</strong></td><td></td></tr>
            @for ($i = 1; $i < 6; $i++)
                <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
            @endfor
        </tbody>
    </table>
@endsection
