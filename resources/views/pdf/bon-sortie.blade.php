{{-- Bon de sortie (cahier §14), reproduit d'après le carnet officiel de l'Université (« RECTORAT — BON DE SORTIE ») :
     Demandeur, Date, colonnes Désignations / Quantités / Observations, signatures Le Demandeur, Le Comptable Matière,
     Visa du Chef de Service des Finances. Ajouts demandés par le cahier : numéro, unité, service, motif, référence du
     document papier (ex. bon du Service Patrimoine). --}}
@php
    $q = fn ($value) => \App\Support\Quantity::format($value);
@endphp
@extends('pdf._layout', [
    'title' => 'Bon de sortie',
    'number' => 'N° '.$sortie->displayNumber(),
    'date' => $sortie->issued_on,
    'stamp' => $sortie->isDraft() ? 'Brouillon — sans effet sur le stock' : ($sortie->status === \App\Enums\DocumentStatus::Annulee ? 'Annulée' : null),
    'signatures' => app(\App\Support\DocumentSettings::class)->signatures('bon_sortie', [
        'demandeur' => $sortie->requester_name, 'service' => $sortie->service?->name,
        'valide_par' => $sortie->validator?->displayName(), 'saisi_par' => $sortie->creator?->displayName(),
    ]),
])

@section('body')
    <table class="info">
        <tr>
            <td class="label">Demandeur :</td><td><strong>{{ $sortie->requester_name }}</strong></td>
            <td class="label">Date :</td><td><strong>{{ $sortie->issued_on->format('d/m/Y') }}</strong></td>
        </tr>
        <tr>
            <td class="label">Service :</td><td>{{ $sortie->service?->name }}</td>
            <td class="label">Réf. document :</td><td>{{ $sortie->paper_reference ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Objet :</td><td colspan="3">{{ $sortie->reason }}</td>
        </tr>
        @if ($sortie->cancelled_at)
            <tr>
                <td class="label">Annulée le :</td><td>{{ $sortie->cancelled_at->format('d/m/Y H:i') }} par {{ $sortie->canceller?->displayName() }}</td>
                <td class="label">Motif :</td><td>{{ $sortie->cancel_reason }}</td>
            </tr>
        @endif
    </table>

    <table class="lines official-grid">
        <thead>
            <tr>
                <th style="width: 5%">N°</th>
                <th>Désignations</th>
                <th style="width: 9%">Unité</th>
                <th class="num" style="width: 11%">Quantités</th>
                <th style="width: 30%">Observations</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sortie->details as $n => $detail)
                <tr>
                    <td>{{ str_pad($n + 1, 2, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $detail->article?->designation }}</td>
                    <td>{{ $detail->article?->unite?->symbol }}</td>
                    <td class="num"><strong>{{ $q($detail->quantity) }}</strong></td>
                    <td>{{ $detail->observation }}</td>
                </tr>
            @endforeach
            {{-- Lignes vides comme sur le carnet, pour une sortie courte (au moins 8 lignes). --}}
            @for ($i = $sortie->details->count(); $i < 8; $i++)
                <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
            @endfor
        </tbody>
    </table>

    @if ($sortie->observation)
        <div class="note"><strong>Observation :</strong> {{ $sortie->observation }}</div>
    @endif
    <div class="note">Établi par {{ $sortie->validator?->displayName() ?? $sortie->creator?->displayName() }}{{ $sortie->validated_at ? ' le '.$sortie->validated_at->format('d/m/Y à H:i') : '' }}.</div>
@endsection
