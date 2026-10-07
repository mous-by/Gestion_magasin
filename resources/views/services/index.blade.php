@extends('layouts.admin')

@section('title', 'Services bénéficiaires')

@section('content')
    @include('referentiels._simple', [
        'title' => 'Services bénéficiaires',
        'heading' => 'SERVICES BÉNÉFICIAIRES',
        'icon' => 'bx bx-buildings',
        'items' => $services,
        'model' => \App\Models\Service::class,
        'routePrefix' => 'services',
        'idField' => 'service_id',
        'deleteTitle' => 'Supprimer ce service ?',
        'newLabel' => 'Nouveau service',
        'fields' => [
            ['name' => 'name', 'label' => 'Nom', 'required' => true, 'max' => 150, 'placeholder' => 'Faculté des Sciences'],
            ['name' => 'code', 'label' => 'Code', 'max' => 30, 'placeholder' => 'FS'],
        ],
        'columns' => [
            ['label' => 'SERVICE', 'value' => fn ($s) => '<div class="fw-semibold">'.e($s->name).'</div>'],
            ['label' => 'CODE', 'value' => fn ($s) => e($s->code ?? '—')],
        ],
    ])
@endsection
