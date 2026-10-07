{{-- Onglets des référentiels (config/referentiels.php) : un seul lien dans le menu, ces onglets relient les écrans voisins. --}}
@php
    $referentielTabs = collect(config('referentiels.tabs'))
        ->filter(fn ($tab) => \Illuminate\Support\Facades\Route::has($tab['route']) && auth()->user()->can($tab['permission']));
@endphp
@if ($referentielTabs->count() > 1)
    <nav class="nv-tabs mb-3" aria-label="Référentiels">
        @foreach ($referentielTabs as $tab)
            <a href="{{ route($tab['route']) }}" class="nv-tab {{ request()->routeIs($tab['active']) ? 'active' : '' }}">
                <i class='{{ $tab['icon'] }}'></i>{{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
@endif
