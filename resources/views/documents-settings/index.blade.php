@extends('layouts.admin')

@section('title', 'Documents')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Paramètres</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Documents</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    @php
        $v = fn (string $key, $default) => old($key, $default);
    @endphp
    <div class="row">
        <div class="col-12 col-lg-3">
            @include('configuration._menu')
        </div>

        <div class="col-12 col-lg-9">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 small">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card">
                <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="mb-0 text-white"><i class='bx bx-image me-2'></i>LOGO</h6>
                    <a href="{{ route('documents.settings.preview') }}" target="_blank" class="btn btn-light btn-sm d-flex align-items-center gap-1"><i class='bx bx-show'></i> Aperçu d'un bon</a>
                </div>
                <div class="card-body d-flex flex-wrap align-items-center gap-4">
                    <div class="nv-panel text-center" style="min-width: 160px">
                        @if ($logo)
                            <img src="{{ $logo }}" alt="Logo actuel" style="max-height: 80px; max-width: 180px">
                        @else
                            <div class="text-muted small">Aucun logo : le nom de la structure s'imprime à sa place.</div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('documents.settings.logo') }}" enctype="multipart/form-data" class="d-flex flex-wrap align-items-end gap-2">
                        @csrf
                        <div>
                            <label for="logo" class="form-label fw-semibold">Nouveau logo (PNG ou JPEG, 1 Mo max.)</label>
                            <input type="file" class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" accept="image/png,image/jpeg" required>
                            @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary"><i class='bx bx-upload me-1'></i>Enregistrer</button>
                    </form>
                    @if ($logo)
                        <form method="POST" action="{{ route('documents.settings.logo.remove') }}" class="js-confirm-delete" data-title="Retirer le logo ?" data-name="Le logo">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger"><i class='bx bx-trash me-1'></i>Retirer</button>
                        </form>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('documents.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="card">
                    <div class="card-header card-header-brand">
                        <h6 class="mb-0 text-white"><i class='bx bx-heading me-2'></i>EN-TÊTE OFFICIEL</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach (\App\Support\DocumentSettings::HEADER as $key => $label)
                                <div class="{{ $key === 'ministry' ? 'col-12' : 'col-md-6' }}">
                                    <label for="header-{{ $key }}" class="form-label fw-semibold">{{ $label }}</label>
                                    <input type="text" class="form-control @error('header.'.$key) is-invalid @enderror" id="header-{{ $key }}" name="header[{{ $key }}]" value="{{ $v('header.'.$key, $header[$key]) }}" maxlength="150" @required($key !== 'service')>
                                    @error('header.'.$key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header card-header-brand">
                        <h6 class="mb-0 text-white"><i class='bx bx-pen me-2'></i>SIGNATAIRES</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">Pour chaque document, trois zones de signature : le <strong>titre</strong> (souligné) et le <strong>nom</strong> imprimé sous l'espace de signature. Le nom peut être fixe (« M. Souleymane MAKADJI ») ou repris du document avec un jeton :</p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach (\App\Support\DocumentSettings::TOKENS as $token => $meaning)
                                <span class="badge bg-primary-subtle" title="{{ $meaning }}">{{ $token }} <span class="fw-normal">— {{ $meaning }}</span></span>
                            @endforeach
                        </div>
                        @foreach (\App\Support\DocumentSettings::DOCUMENTS as $doc => $docLabel)
                            <div class="nv-panel mb-3">
                                <div class="nv-panel-title">{{ $docLabel }}</div>
                                <div class="row g-2">
                                    @foreach ($signatures[$doc] as $i => $slot)
                                        <div class="col-md-4">
                                            <input type="text" class="form-control form-control-sm mb-1" name="signatures[{{ $doc }}][{{ $i }}][label]" value="{{ $v("signatures.$doc.$i.label", $slot['label']) }}" maxlength="80" placeholder="Titre (vide = zone vide)" aria-label="{{ $docLabel }} — titre {{ $i + 1 }}">
                                            <input type="text" class="form-control form-control-sm" name="signatures[{{ $doc }}][{{ $i }}][name]" value="{{ $v("signatures.$doc.$i.name", $slot['name']) }}" maxlength="120" placeholder="Nom (facultatif)" aria-label="{{ $docLabel }} — nom {{ $i + 1 }}">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="card">
                    <div class="card-header card-header-brand">
                        <h6 class="mb-0 text-white"><i class='bx bx-hash me-2'></i>NUMÉROTATION</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">Jetons : <code>{prefix}</code>, <code>{year}</code>, <code>{number}</code> (obligatoires : {year} et {number}). Exemples : <code>{prefix}-{year}-{number}</code> → SOR-2026-0001 ; style du Service Patrimoine <code>{year}-{number}/US-{prefix}</code> avec 3 chiffres → 2026-029/US-SOR. Le compteur repart à 1 chaque année ; les documents déjà validés gardent leur numéro.</p>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label for="numbering-format" class="form-label fw-semibold">Format</label>
                                <input type="text" class="form-control @error('numbering.format') is-invalid @enderror" id="numbering-format" name="numbering[format]" value="{{ $v('numbering.format', $numbering['format']) }}" maxlength="60" required>
                                @error('numbering.format')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-2">
                                <label for="numbering-padding" class="form-label fw-semibold">Chiffres</label>
                                <input type="number" min="1" max="8" class="form-control @error('numbering.padding') is-invalid @enderror" id="numbering-padding" name="numbering[padding]" value="{{ $v('numbering.padding', $numbering['padding']) }}" required>
                            </div>
                            @foreach (['entree' => 'Préfixe entrées', 'sortie' => 'Préfixe sorties', 'regularisation' => 'Préfixe régularisations'] as $type => $label)
                                <div class="col-md-{{ $type === 'regularisation' ? 3 : 'auto' }}">
                                    <label for="prefix-{{ $type }}" class="form-label fw-semibold">{{ $label }}</label>
                                    <input type="text" class="form-control" id="prefix-{{ $type }}" name="numbering[prefixes][{{ $type }}]" value="{{ $v("numbering.prefixes.$type", $numbering['prefixes'][$type]) }}" maxlength="10" required style="max-width: 140px">
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a href="{{ route('documents.settings.preview') }}" target="_blank" class="btn btn-outline-primary"><i class='bx bx-show me-1'></i>Aperçu</a>
                        <button type="submit" class="btn btn-primary"><i class='bx bx-save me-1'></i>Enregistrer les paramètres</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.confirm-delete')
@endpush
