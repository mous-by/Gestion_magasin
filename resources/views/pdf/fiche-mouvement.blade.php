{{-- Fiche de mouvement d'un article (cahier §15, §18) : date, référence, opération, entrée, sortie, solde. --}}
@php
    $q = fn ($value) => \App\Support\Quantity::format($value);
    $period = isset($filters['du']) || isset($filters['au'])
        ? 'Période : '.(isset($filters['du']) ? 'du '.\Illuminate\Support\Carbon::parse($filters['du'])->format('d/m/Y') : 'depuis l\'origine').(isset($filters['au']) ? ' au '.\Illuminate\Support\Carbon::parse($filters['au'])->format('d/m/Y') : '')
        : 'Tout l\'historique';
@endphp
@extends('pdf._layout', [
    'title' => 'Fiche de mouvement',
    'number' => $article->reference,
    'signatures' => app(\App\Support\DocumentSettings::class)->signatures('fiche_mouvement'),
])

@section('body')
    <table class="info">
        <tr><td class="label">Article</td><td>{{ $article->designation }}</td><td class="label">Catégorie</td><td>{{ $article->category?->name }}</td></tr>
        <tr><td class="label">Unité</td><td>{{ $article->unite?->name }}</td><td class="label">Stock actuel</td><td><strong>{{ $article->stockForDisplay() }}</strong></td></tr>
        <tr><td class="label">Seuil minimum</td><td>{{ $article->thresholdForDisplay() ?? '—' }}</td><td class="label">Période</td><td>{{ $period }}</td></tr>
    </table>

    <table class="lines">
        <thead>
            <tr><th>Date</th><th>Référence</th><th>Opération</th><th class="num">Entrée</th><th class="num">Sortie</th><th class="num">Solde</th><th>Par</th></tr>
        </thead>
        <tbody>
            @if (isset($filters['du']))
                <tr><td colspan="5"><em>Solde d'ouverture</em></td><td class="num"><strong>{{ $q($opening) }}</strong></td><td></td></tr>
            @endif
            @forelse ($rows as $row)
                @php
                    $m = $row['mouvement'];
                @endphp
                <tr>
                    <td>{{ $m->moved_on->format('d/m/Y') }}</td>
                    <td>{{ $m->reference }}</td>
                    <td>{{ $m->type->label() }}{{ $m->reason ? ' — '.$m->reason : '' }}</td>
                    <td class="num">{{ (float) $m->quantity > 0 ? $q($m->quantity) : '—' }}</td>
                    <td class="num">{{ (float) $m->quantity < 0 ? $q(-1 * (float) $m->quantity) : '—' }}</td>
                    <td class="num"><strong>{{ $q($row['balance']) }}</strong></td>
                    <td>{{ $m->user?->displayName() }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Aucun mouvement sur la période.</td></tr>
            @endforelse
            @if ($rows->isNotEmpty())
                <tr><td colspan="3"><strong>Totaux</strong></td><td class="num"><strong>{{ $q($totalIn) }}</strong></td><td class="num"><strong>{{ $q($totalOut) }}</strong></td><td class="num"><strong>{{ $q($closing) }}</strong></td><td></td></tr>
            @endif
        </tbody>
    </table>
@endsection
