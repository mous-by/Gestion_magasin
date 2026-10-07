@extends('layouts.admin')

@section('title', 'Unités')

@section('content')
    @include('referentiels._simple', [
        'title' => 'Unités',
        'heading' => 'UNITÉS DE MESURE',
        'icon' => 'bx bx-ruler',
        'items' => $unites,
        'model' => \App\Models\Unite::class,
        'routePrefix' => 'unites',
        'idField' => 'unite_id',
        'deleteTitle' => 'Supprimer cette unité ?',
        'newLabel' => 'Nouvelle unité',
        'fields' => [
            ['name' => 'name', 'label' => 'Nom', 'required' => true, 'max' => 50, 'placeholder' => 'Rouleau'],
            ['name' => 'symbol', 'label' => 'Symbole', 'required' => true, 'max' => 15, 'placeholder' => 'rlx'],
            ['name' => 'allows_decimal', 'label' => 'Quantités décimales admises (mètre, litre…)', 'type' => 'switch'],
        ],
        'columns' => [
            ['label' => 'UNITÉ', 'value' => fn ($u) => '<div class="fw-semibold">'.e($u->name).'</div><div class="small text-muted">'.e($u->symbol).'</div>'],
            ['label' => 'DÉCIMALES', 'value' => fn ($u) => $u->allows_decimal ? 'Oui' : 'Non'],
            ['label' => 'ARTICLES', 'value' => fn ($u) => e($u->articles_count)],
        ],
    ])
@endsection
