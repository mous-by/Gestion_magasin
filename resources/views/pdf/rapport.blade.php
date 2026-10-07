{{-- Rapport générique (cahier §18) : titre, filtres en clair, tableau, totaux. Données : RapportBuilder. --}}
@extends('pdf._layout', [
    'title' => $report['title'],
    'number' => $report['subtitle'],
    'signatures' => app(\App\Support\DocumentSettings::class)->signatures('rapport'),
])

@section('body')
    <table class="lines">
        <thead>
            <tr>
                @foreach ($report['columns'] as $i => $column)
                    <th @class(['num' => in_array($i, $report['numeric'], true)])>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($row as $i => $cell)
                        <td @class(['num' => in_array($i, $report['numeric'], true)])>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}">Aucune donnée pour ces critères.</td></tr>
            @endforelse
            @if ($report['totals'] && $report['rows'] !== [])
                <tr>
                    @foreach ($report['totals'] as $cell)
                        <td><strong>{{ $cell }}</strong></td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
    <div class="note">Seules les opérations validées sont prises en compte ; une opération annulée est neutralisée par son mouvement d'annulation.</div>
@endsection
