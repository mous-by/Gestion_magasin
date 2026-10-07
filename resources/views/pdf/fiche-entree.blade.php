{{-- Fiche d'entrée / réception (cahier §18). Modèle définitif à valider avec le service (cahier §33). --}}
@php
    $planned = $entree->isPlanned();
    $q = fn ($value) => \App\Support\Quantity::format($value);
@endphp
@extends('pdf._layout', [
    'title' => 'Fiche de réception',
    'number' => 'N° '.$entree->displayNumber(),
    'date' => $entree->received_on,
    'stamp' => $entree->isDraft() ? 'Brouillon — sans effet sur le stock' : ($entree->status === \App\Enums\DocumentStatus::Annulee ? 'Annulée' : null),
    'signatures' => app(\App\Support\DocumentSettings::class)->signatures('fiche_entree', [
        'fournisseur' => $entree->fournisseur?->name, 'valide_par' => $entree->validator?->displayName(), 'saisi_par' => $entree->creator?->displayName(),
    ]),
])

@section('body')
    <table class="info">
        <tr>
            <td class="label">Date de réception</td><td>{{ $entree->received_on->format('d/m/Y') }}</td>
            <td class="label">Fournisseur</td><td>{{ $entree->fournisseur?->name }}</td>
        </tr>
        <tr>
            <td class="label">N° bordereau</td><td>{{ $entree->delivery_note_number ?? '—' }}</td>
            <td class="label">Réf. commande</td><td>{{ $entree->order_reference ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Saisie par</td><td>{{ $entree->creator?->displayName() }}</td>
            <td class="label">Validée par</td><td>{{ $entree->validator ? $entree->validator->name.' le '.$entree->validated_at->format('d/m/Y H:i') : '—' }}</td>
        </tr>
        @if ($entree->cancelled_at)
            <tr>
                <td class="label">Annulée le</td><td>{{ $entree->cancelled_at->format('d/m/Y H:i') }} par {{ $entree->canceller?->displayName() }}</td>
                <td class="label">Motif</td><td>{{ $entree->cancel_reason }}</td>
            </tr>
        @endif
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th style="width: 4%">N°</th>
                <th>Désignation</th>
                <th style="width: 7%">Unité</th>
                <th class="num" style="width: 8%">Prévu</th>
                <th class="num" style="width: 8%">Livré</th>
                <th class="num" style="width: 9%">Accepté</th>
                <th class="num" style="width: 8%">Rejeté</th>
                <th style="width: 25%">Observations</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entree->details as $n => $detail)
                @php
                    $discrepancies = $detail->discrepancies($planned);
                @endphp
                <tr @class(['gap' => $discrepancies !== []])>
                    <td>{{ $n + 1 }}</td>
                    <td>{{ $detail->article?->designation }}<br><span style="color:#6b7280">{{ $detail->article?->reference }}</span></td>
                    <td>{{ $detail->article?->unite?->symbol }}</td>
                    <td class="num">{{ $q($detail->expected_qty) ?? '—' }}</td>
                    <td class="num">{{ $q($detail->delivered_qty) }}</td>
                    <td class="num"><strong>{{ $q($detail->accepted_qty) }}</strong></td>
                    <td class="num">{{ (float) $detail->rejected_qty > 0 ? $q($detail->rejected_qty) : '—' }}</td>
                    <td>
                        @if ($discrepancies !== [])
                            <strong>{{ implode(', ', $discrepancies) }}.</strong>
                        @endif
                        {{ $detail->observation }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($entree->observation)
        <div class="note"><strong>Observation générale :</strong> {{ $entree->observation }}</div>
    @endif
    @if ($entree->discrepancy_resolved_at)
        <div class="note"><strong>Suite donnée à l'écart</strong> ({{ $entree->discrepancy_resolved_at->format('d/m/Y') }}, {{ $entree->resolver?->displayName() }}) : {{ $entree->discrepancy_resolution }}</div>
    @endif
    <div class="note">Seules les quantités acceptées sont entrées en stock. Les lignes surlignées comportent un écart de livraison.</div>
@endsection
