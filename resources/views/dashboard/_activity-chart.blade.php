{{-- Activité des 14 derniers jours : entrées et sorties validées par jour. Colonnes groupées, une seule échelle,
     SVG rendu côté serveur (aucune bibliothèque, aucun CDN). Palette validée (2 séries, clair et sombre) ; légende,
     infobulle au survol et vue tableau pour l'accessibilité. $chart : RealDashboardData::activity(). --}}
@php
    $days = $chart['days'];
    $n = count($days);
    $step = $chart['max'] <= 5 ? 1 : (int) ceil($chart['max'] / 4);
    $top = (int) (ceil($chart['max'] / $step) * $step);
    [$w, $h, $left, $right, $plotTop, $plotBottom] = [720, 190, 34, 8, 12, 156];
    $band = ($w - $left - $right) / $n;
    $barW = min(16, $band * 0.3);
    $y = fn (float $v) => $plotBottom - ($v / $top) * ($plotBottom - $plotTop);
    // Colonne arrondie (4 px) au sommet, carrée sur la ligne de base.
    $bar = function (float $x, float $value) use ($y, $barW, $plotBottom) {
        if ($value <= 0) {
            return '';
        }
        $yt = $y($value);
        $r = min(4, ($plotBottom - $yt) / 2, $barW / 2);

        return sprintf('M%.1f,%.1f V%.1f Q%.1f,%.1f %.1f,%.1f H%.1f Q%.1f,%.1f %.1f,%.1f V%.1f Z',
            $x, $plotBottom, $yt + $r, $x, $yt, $x + $r, $yt, $x + $barW - $r, $x + $barW, $yt, $x + $barW, $yt + $r, $plotBottom);
    };
@endphp
<div class="card viz-root">
    <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h6 class="text-white mb-0"><i class='bx bx-bar-chart-alt-2 me-2'></i>ACTIVITÉ DES 14 DERNIERS JOURS</h6>
        <button type="button" class="btn btn-light btn-sm" id="activity-toggle" aria-pressed="false"><i class='bx bx-table me-1'></i>Tableau</button>
    </div>
    <div class="card-body">
        <div class="viz-legend mb-2" aria-hidden="true">
            <span><i class="viz-swatch viz-s1"></i>Entrées validées <b>{{ $chart['total_entrees'] }}</b></span>
            <span><i class="viz-swatch viz-s2"></i>Sorties validées <b>{{ $chart['total_sorties'] }}</b></span>
        </div>

        @if ($chart['total_entrees'] + $chart['total_sorties'] === 0)
            <p class="text-muted mb-0">Aucune entrée ni sortie validée sur les 14 derniers jours.</p>
        @else
            <div class="viz-figure position-relative" id="activity-chart">
                <svg viewBox="0 0 {{ $w }} {{ $h }}" role="img" aria-label="Entrées et sorties validées par jour, du {{ $days[0]['label'] }} au {{ $days[$n - 1]['label'] }} : {{ $chart['total_entrees'] }} entrées, {{ $chart['total_sorties'] }} sorties." class="w-100 h-auto">
                    @for ($t = 0; $t <= $top; $t += $step)
                        <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $y($t) }}" y2="{{ $y($t) }}" class="{{ $t === 0 ? 'viz-axis' : 'viz-grid' }}" />
                        <text x="{{ $left - 6 }}" y="{{ $y($t) + 3 }}" text-anchor="end" class="viz-tick">{{ $t }}</text>
                    @endfor
                    @foreach ($days as $i => $day)
                        @php
                            $x0 = $left + $i * $band + ($band - (2 * $barW + 2)) / 2;
                        @endphp
                        <path d="{{ $bar($x0, $day['entrees']) }}" class="viz-s1" />
                        <path d="{{ $bar($x0 + $barW + 2, $day['sorties']) }}" class="viz-s2" />
                        <text x="{{ $left + $i * $band + $band / 2 }}" y="{{ $plotBottom + 16 }}" text-anchor="middle" class="viz-tick">{{ $day['label'] }}</text>
                        <rect x="{{ $left + $i * $band }}" y="{{ $plotTop }}" width="{{ $band }}" height="{{ $plotBottom - $plotTop }}" class="viz-hit"
                              data-label="{{ $day['label'] }}" data-entrees="{{ $day['entrees'] }}" data-sorties="{{ $day['sorties'] }}" />
                    @endforeach
                </svg>
                <div class="viz-tooltip d-none" role="status"></div>
            </div>
        @endif

        <div class="table-responsive d-none mt-2" id="activity-table">
            <table class="table table-sm">
                <thead><tr><th>Jour</th><th class="text-end">Entrées validées</th><th class="text-end">Sorties validées</th></tr></thead>
                <tbody>
                    @foreach ($days as $day)
                        <tr><td>{{ $day['label'] }}</td><td class="text-end">{{ $day['entrees'] }}</td><td class="text-end">{{ $day['sorties'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('styles')
    <style>
        .viz-root { --series-1: #2a78d6; --series-2: #eb6834; --viz-grid: #e5e7eb; --viz-axis: #9ca3af; --viz-text: #52514e; --viz-tip-bg: #ffffff; --viz-tip-fg: #0b0b0b; }
        html.dark-theme .viz-root { --series-1: #3987e5; --series-2: #d95926; --viz-grid: #2f3640; --viz-axis: #6b7280; --viz-text: #c3c2b7; --viz-tip-bg: #1a1a19; --viz-tip-fg: #ffffff; }
        .viz-root .viz-s1 { fill: var(--series-1); background: var(--series-1); }
        .viz-root .viz-s2 { fill: var(--series-2); background: var(--series-2); }
        .viz-root .viz-grid { stroke: var(--viz-grid); stroke-width: 1; }
        .viz-root .viz-axis { stroke: var(--viz-axis); stroke-width: 1; }
        .viz-root .viz-tick { fill: var(--viz-text); font-size: 10px; }
        .viz-root .viz-hit { fill: transparent; cursor: crosshair; }
        .viz-root .viz-hit:hover { fill: var(--viz-grid); fill-opacity: .45; }
        .viz-root .viz-legend { display: flex; flex-wrap: wrap; gap: 1.25rem; font-size: .82rem; color: var(--viz-text); }
        .viz-root .viz-swatch { display: inline-block; width: 12px; height: 12px; border-radius: 3px; margin-right: .4rem; vertical-align: -1px; }
        .viz-root .viz-tooltip { position: absolute; pointer-events: none; background: var(--viz-tip-bg); color: var(--viz-tip-fg); border: 1px solid var(--viz-grid); border-radius: 8px; padding: .4rem .6rem; font-size: .8rem; box-shadow: 0 4px 14px rgba(0,0,0,.12); white-space: nowrap; }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            const figure = document.getElementById('activity-chart');
            const tableView = document.getElementById('activity-table');
            document.getElementById('activity-toggle').addEventListener('click', function () {
                const showTable = tableView.classList.toggle('d-none') === false;
                figure?.classList.toggle('d-none', showTable);
                this.setAttribute('aria-pressed', String(showTable));
                this.innerHTML = showTable ? "<i class='bx bx-bar-chart-alt-2 me-1'></i>Graphique" : "<i class='bx bx-table me-1'></i>Tableau";
            });
            if (!figure) return;
            const tip = figure.querySelector('.viz-tooltip');
            figure.querySelectorAll('.viz-hit').forEach((hit) => {
                hit.addEventListener('mousemove', (e) => {
                    const box = figure.getBoundingClientRect();
                    tip.innerHTML = '<strong>' + hit.dataset.label + '</strong><br><i class="viz-swatch viz-s1"></i>Entrées : ' + hit.dataset.entrees + '<br><i class="viz-swatch viz-s2"></i>Sorties : ' + hit.dataset.sorties;
                    tip.classList.remove('d-none');
                    const x = Math.min(e.clientX - box.left + 12, box.width - tip.offsetWidth - 4);
                    tip.style.left = Math.max(4, x) + 'px';
                    tip.style.top = Math.max(0, e.clientY - box.top - tip.offsetHeight - 8) + 'px';
                });
                hit.addEventListener('mouseleave', () => tip.classList.add('d-none'));
            });
        })();
    </script>
@endpush
