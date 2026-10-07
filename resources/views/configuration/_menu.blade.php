{{-- Sous-menu « Paramètres » (list-group) : entrées visibles selon les permissions et les routes existantes.
     Liste des écrans (route, permission, libellé, icône, motif actif) : App\Support\ModuleRegistry::settingsItems(),
     source unique partagée avec la sidebar (ModuleServiceProvider), pour ne jamais désynchroniser les deux. --}}
<div class="card">
    <div class="card-header card-header-brand">
        <h6 class="mb-0 text-white"><i class='bx bx-cog me-2'></i>PARAMÈTRES</h6>
    </div>
    <div class="card-body">
        <div class="list-group">
            @foreach (app(\App\Support\ModuleRegistry::class)->settingsItems() as $item)
                @if (Route::has($item['route']) && auth()->user()->can($item['permission']))
                    <a href="{{ route($item['route']) }}" class="list-group-item list-group-item-action {{ request()->routeIs($item['active']) ? 'active' : '' }}">
                        <i class='{{ $item['icon'] }} me-2'></i>{{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>
