@extends('layouts.admin')

@section('title', 'Catégories')

@section('content')
    @include('referentiels._simple', [
        'title' => 'Catégories',
        'heading' => 'CATÉGORIES D\'ARTICLES',
        'icon' => 'bx bx-category',
        'items' => $categories,
        'model' => \App\Models\Category::class,
        'routePrefix' => 'categories',
        'idField' => 'category_id',
        'deleteTitle' => 'Supprimer cette catégorie ?',
        'newLabel' => 'Nouvelle catégorie',
        'fields' => [
            ['name' => 'name', 'label' => 'Nom', 'required' => true, 'max' => 100, 'placeholder' => 'Électricité'],
            ['name' => 'description', 'label' => 'Description', 'max' => 255],
        ],
        'columns' => [
            ['label' => 'CATÉGORIE', 'value' => fn ($c) => '<div class="fw-semibold">'.e($c->name).'</div>'.($c->description ? '<div class="small text-muted">'.e($c->description).'</div>' : '')],
            ['label' => 'ARTICLES', 'value' => fn ($c) => e($c->articles_count)],
        ],
    ])
@endsection
