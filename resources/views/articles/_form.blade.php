{{-- Champs de la fiche article (cahier §9), partagés par la création et la modification.
     $article : null en création ; $failed : vrai si ce formulaire revient avec des erreurs ; $sfx : suffixe des id. --}}
@php
    $val = fn (string $field) => $failed ? old($field) : $article?->{$field};
    $err = fn (string $field) => $failed && $errors->has($field);
    $currentCategory = $article?->category;
    $currentUnite = $article?->unite;
@endphp
<div class="row g-3">
    <div class="col-md-4">
        <label for="reference{{ $sfx }}" class="form-label fw-semibold">Référence <span class="text-danger">*</span></label>
        <input type="text" class="form-control text-uppercase @if ($err('reference')) is-invalid @endif" id="reference{{ $sfx }}" name="reference" value="{{ $val('reference') }}" maxlength="50" placeholder="ELEC-0001" required>
        @if ($err('reference'))<div class="invalid-feedback">{{ $errors->first('reference') }}</div>@endif
    </div>
    <div class="col-md-8">
        <label for="designation{{ $sfx }}" class="form-label fw-semibold">Désignation <span class="text-danger">*</span></label>
        <input type="text" class="form-control @if ($err('designation')) is-invalid @endif" id="designation{{ $sfx }}" name="designation" value="{{ $val('designation') }}" maxlength="200" placeholder="Réglette 1,20 m LED" required>
        @if ($err('designation'))<div class="invalid-feedback">{{ $errors->first('designation') }}</div>@endif
    </div>
    <div class="col-md-6">
        <label for="category_id{{ $sfx }}" class="form-label fw-semibold">Catégorie <span class="text-danger">*</span></label>
        <select class="form-select single-select" id="category_id{{ $sfx }}" name="category_id" required>
            <option value="">— Choisir —</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) $val('category_id') === $category->id)>{{ $category->name }}</option>
            @endforeach
            @if ($currentCategory && ! $categories->contains('id', $currentCategory->id))
                <option value="{{ $currentCategory->id }}" @selected((int) $val('category_id') === $currentCategory->id)>{{ $currentCategory->name }} (inactive)</option>
            @endif
        </select>
        @if ($err('category_id'))<div class="text-danger small mt-1">{{ $errors->first('category_id') }}</div>@endif
    </div>
    <div class="col-md-6">
        <label for="unite_id{{ $sfx }}" class="form-label fw-semibold">Unité <span class="text-danger">*</span></label>
        <select class="form-select single-select" id="unite_id{{ $sfx }}" name="unite_id" required>
            <option value="">— Choisir —</option>
            @foreach ($unites as $unite)
                <option value="{{ $unite->id }}" @selected((int) $val('unite_id') === $unite->id)>{{ $unite->name }} ({{ $unite->symbol }})</option>
            @endforeach
            @if ($currentUnite && ! $unites->contains('id', $currentUnite->id))
                <option value="{{ $currentUnite->id }}" @selected((int) $val('unite_id') === $currentUnite->id)>{{ $currentUnite->name }} (inactive)</option>
            @endif
        </select>
        @if ($err('unite_id'))<div class="text-danger small mt-1">{{ $errors->first('unite_id') }}</div>@endif
    </div>
    <div class="col-md-6">
        <label for="brand_model{{ $sfx }}" class="form-label fw-semibold">Marque / modèle</label>
        <input type="text" class="form-control @if ($err('brand_model')) is-invalid @endif" id="brand_model{{ $sfx }}" name="brand_model" value="{{ $val('brand_model') }}" maxlength="150">
        @if ($err('brand_model'))<div class="invalid-feedback">{{ $errors->first('brand_model') }}</div>@endif
    </div>
    <div class="col-md-3">
        <label for="min_threshold{{ $sfx }}" class="form-label fw-semibold">Seuil minimum</label>
        <input type="text" inputmode="decimal" class="form-control @if ($err('min_threshold')) is-invalid @endif" id="min_threshold{{ $sfx }}" name="min_threshold" value="{{ $failed ? old('min_threshold') : $article?->thresholdForDisplay() }}" placeholder="Ex. 10">
        @if ($err('min_threshold'))<div class="invalid-feedback">{{ $errors->first('min_threshold') }}</div>@endif
    </div>
    <div class="col-md-3">
        <label for="location{{ $sfx }}" class="form-label fw-semibold">Emplacement</label>
        <input type="text" class="form-control @if ($err('location')) is-invalid @endif" id="location{{ $sfx }}" name="location" value="{{ $val('location') }}" maxlength="100" placeholder="Rayon A2">
        @if ($err('location'))<div class="invalid-feedback">{{ $errors->first('location') }}</div>@endif
    </div>
    <div class="col-12">
        <label for="observation{{ $sfx }}" class="form-label fw-semibold">Observation</label>
        <textarea class="form-control @if ($err('observation')) is-invalid @endif" id="observation{{ $sfx }}" name="observation" rows="2" maxlength="2000">{{ $val('observation') }}</textarea>
        @if ($err('observation'))<div class="invalid-feedback">{{ $errors->first('observation') }}</div>@endif
    </div>
    @if ($article)
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="active{{ $sfx }}" name="is_active" value="1" @checked($failed ? old('is_active') : $article->is_active)>
                <label class="form-check-label" for="active{{ $sfx }}">Article actif</label>
            </div>
            <small class="text-muted">Un article inactif reste dans l'historique mais ne peut plus être utilisé dans une nouvelle entrée ou sortie.</small>
        </div>
    @endif
</div>
