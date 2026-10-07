@extends('layouts.admin')

@section('title', 'Tableau de bord')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Tableau de bord</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><i class="bx bx-home-alt"></i></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $dashboard['heading'] }}</li>
                </ol>
            </nav>
        </div>
        {{-- Accès direct à « Nouvelle entrée » et « Nouvelle sortie » (cahier §8), selon les permissions. --}}
        <div class="ms-auto d-flex flex-wrap gap-2">
            @foreach (array_slice($dashboard['quick'], 0, 2) as $link)
                <a href="{{ route($link['route'], $link['params']) }}" class="btn btn-primary btn-sm"><i class='{{ $link['icon'] }} me-1'></i>{{ $link['label'] }}</a>
            @endforeach
        </div>
    </div>
    <hr />

    @include('dashboard._body')
@endsection
