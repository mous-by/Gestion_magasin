@extends('layouts.admin')

@section('title', 'Articles')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Articles</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Catalogue des articles</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />
    @include('partials.referentiels-tabs')

    <div class="card">
        <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 text-white"><i class='bx bx-package me-2'></i>CATALOGUE DES ARTICLES</h6>
            <div class="d-flex flex-wrap gap-2">
                @can('articles.import')
                    <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#importArticlesModal">
                        <i class='bx bx-spreadsheet'></i> Importer (Excel)
                    </button>
                @endcan
                @can('create', \App\Models\Article::class)
                    <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#createArticleModal">
                        <i class='bx bx-plus'></i> Ajouter
                    </button>
                @endcan
            </div>
        </div>
        <div class="card-body">
            @if (session('import_errors'))
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-2"><i class='bx bx-error-circle me-1'></i>Import refusé — aucun article n'a été enregistré. Erreurs à corriger dans le fichier :</div>
                    <div class="table-responsive" style="max-height: 320px">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>LIGNE</th><th>COLONNE</th><th>ERREUR</th></tr></thead>
                            <tbody>
                                @foreach (session('import_errors') as $importError)
                                    <tr><td>{{ $importError['line'] ?: '—' }}</td><td>{{ $importError['column'] }}</td><td class="text-break" style="white-space: normal">{{ $importError['message'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
            @if ($categories->isEmpty() || $unites->isEmpty())
                <div class="alert alert-warning py-2">
                    <i class='bx bx-info-circle me-1'></i>
                    Avant de créer un article, il faut au moins une catégorie et une unité actives
                    @can('viewAny', \App\Models\Category::class)
                        (onglets <a href="{{ route('categories.index') }}">Catégories</a> et <a href="{{ route('unites.index') }}">Unités</a>)
                    @endcan.
                </div>
            @endif
            @php
                $activeCount = $articles->where('is_active', true)->count();
                $usedCategories = $articles->pluck('category')->filter()->unique('id')->sortBy('name');
            @endphp
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="nv-chips" id="articles-filter">
                    <button type="button" class="nv-chip active" data-token="">Tous <b>{{ $articles->count() }}</b></button>
                    <button type="button" class="nv-chip" data-token="statut-actif">Actifs <b>{{ $activeCount }}</b></button>
                    <button type="button" class="nv-chip" data-token="statut-inactif">Inactifs <b>{{ $articles->count() - $activeCount }}</b></button>
                    <button type="button" class="nv-chip" data-token="sous-seuil">Sous le seuil <b>{{ $articles->filter(fn ($a) => $a->stockLevel() === 'sous-seuil')->count() }}</b></button>
                    <button type="button" class="nv-chip" data-token="rupture">En rupture <b>{{ $articles->filter(fn ($a) => $a->stockLevel() === 'rupture')->count() }}</b></button>
                    <button type="button" class="nv-chip" data-token="sans-seuil">Sans seuil <b>{{ $articles->whereNull('min_threshold')->count() }}</b></button>
                </div>
                <div style="min-width: 220px">
                    <select class="form-select form-select-sm" id="articles-category-filter" aria-label="Filtrer par catégorie">
                        <option value="">Toutes les catégories</option>
                        @foreach ($usedCategories as $category)
                            <option value="cat-{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table" id="articles-table">
                    <thead>
                        <tr>
                            <th>ARTICLE</th>
                            <th>CATÉGORIE</th>
                            <th>UNITÉ</th>
                            <th>STOCK</th>
                            <th>SEUIL</th>
                            <th>EMPLACEMENT</th>
                            <th>STATUT</th>
                            <th width="12%">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($articles as $article)
                            <tr>
                                <td class="cell-wrap text-break">
                                    <div class="fw-semibold">{{ $article->designation }}</div>
                                    <div class="small text-muted">{{ $article->reference }}@if ($article->brand_model) · {{ $article->brand_model }}@endif</div>
                                </td>
                                <td><span class="d-none">cat-{{ $article->category_id }}</span>{{ $article->category?->name ?? '—' }}</td>
                                <td>{{ $article->unite?->symbol ?? '—' }}</td>
                                <td data-order="{{ (float) $article->stock_quantity }}">
                                    @php
                                        $level = $article->stockLevel();
                                    @endphp
                                    <span class="fw-semibold {{ $level === 'rupture' ? 'text-danger' : ($level === 'sous-seuil' ? 'text-warning' : '') }}">{{ $article->stockForDisplay() }}</span>
                                    @if ($level !== 'normal')
                                        <div class="small {{ $level === 'rupture' ? 'text-danger' : 'text-warning' }}">{{ $level === 'rupture' ? 'Rupture' : 'Sous le seuil' }}</div>
                                    @endif
                                </td>
                                <td>{{ $article->thresholdForDisplay() ?? '—' }}</td>
                                <td>{{ $article->location ?? '—' }}</td>
                                <td>
                                    <span class="d-none">{{ $article->is_active ? 'statut-actif' : 'statut-inactif' }}{{ $article->min_threshold === null ? ' sans-seuil' : '' }} {{ $article->stockLevel() }}</span>
                                    @if ($article->is_active)
                                        <span class="badge bg-success-subtle">Actif</span>
                                    @else
                                        <span class="badge bg-secondary-subtle">Inactif</span>
                                    @endif
                                </td>
                                <td class="d-flex gap-1">
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ficheArticle-{{ $article->id }}" title="Fiche de l'article">
                                        <i class='bx bx-show'></i>
                                    </button>
                                    @can('update', $article)
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editArticle-{{ $article->id }}" title="Modifier">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                    @endcan
                                    @can('delete', $article)
                                        <form method="POST" action="{{ route('articles.destroy', $article) }}" class="js-confirm-delete" data-title="Supprimer cet article ?" data-name="{{ $article->label() }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-primary btn-sm" title="Supprimer">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @foreach ($articles as $article)
        @include('articles._fiche', ['article' => $article])
    @endforeach

    @can('articles.import')
        <div class="modal fade" id="importArticlesModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('articles.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title"><i class='bx bx-spreadsheet me-2'></i>Importer des articles</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body p-4">
                            <ol class="small ps-3">
                                <li>Téléchargez le modèle : il contient les catégories et unités de l'application en listes déroulantes.</li>
                                <li>Remplissez la feuille « Articles » (une ligne par article ; mode d'emploi dans le fichier).</li>
                                <li>Envoyez le fichier : s'il contient une erreur, rien n'est importé et les erreurs vous sont listées.</li>
                            </ol>
                            <a href="{{ route('articles.import.template') }}" class="btn btn-outline-primary btn-sm mb-3"><i class='bx bx-download me-1'></i>Télécharger le modèle Excel</a>
                            <div class="mb-3">
                                <label for="fichier" class="form-label fw-semibold">Fichier rempli (.xlsx, .xls, .ods ou .csv)</label>
                                <input type="file" class="form-control @error('fichier') is-invalid @enderror" id="fichier" name="fichier" accept=".xlsx,.xls,.ods,.csv" required>
                                @error('fichier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="mettre_a_jour" name="mettre_a_jour" value="1">
                                <label class="form-check-label" for="mettre_a_jour">Mettre à jour les articles existants (même référence)</label>
                            </div>
                            <small class="text-muted">Sans cette case, une référence déjà connue est ignorée. Le stock ne s'importe pas : il se constitue par les entrées.</small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary"><i class='bx bx-upload me-1'></i>Importer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @if ($errors->has('fichier'))
            <script>
                window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('importArticlesModal')).show());
            </script>
        @endif
    @endcan

    @can('create', \App\Models\Article::class)
        @php
            $createFailed = $errors->any() && ! old('article_id') && ! $errors->has('fichier');
        @endphp
        <div class="modal fade" id="createArticleModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form method="POST" action="{{ route('articles.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title"><i class='bx bx-package me-2'></i>Nouvel article</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body p-4">
                            @include('articles._form', ['article' => null, 'failed' => $createFailed, 'sfx' => ''])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ($createFailed)
            <script>
                window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('createArticleModal')).show());
            </script>
        @endif
    @endcan

    @foreach ($articles as $article)
        @can('update', $article)
            @php
                $editFailed = $errors->any() && old('article_id') == $article->id;
            @endphp
            <div class="modal fade" id="editArticle-{{ $article->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('articles.update', $article) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="article_id" value="{{ $article->id }}">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class='bx bx-package me-2'></i>Modifier — {{ $article->reference }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                            </div>
                            <div class="modal-body p-4">
                                @include('articles._form', ['article' => $article, 'failed' => $editFailed, 'sfx' => '-'.$article->id])
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" class="btn btn-primary">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if ($editFailed)
                <script>
                    window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('editArticle-{{ $article->id }}')).show());
                </script>
            @endif
        @endcan
    @endforeach
@endsection

@push('scripts')
    <script>
        const articlesTable = $('#articles-table').DataTable({ scrollX: false });

        // Pastilles : jetons cachés dans la colonne « Statut » (statut, seuil, niveau de stock) ; catégorie : jeton caché
        // dans la colonne « Catégorie ».
        document.querySelectorAll('#articles-filter .nv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('#articles-filter .nv-chip').forEach((other) => other.classList.toggle('active', other === chip));
                articlesTable.column(6).search(chip.dataset.token || '', false, true).draw();
            });
        });
        $('#articles-category-filter').on('change', function () {
            articlesTable.column(1).search(this.value ? '\\b' + this.value + '\\b' : '', true, false).draw();
        });
    </script>
    @include('partials.confirm-delete')
@endpush
