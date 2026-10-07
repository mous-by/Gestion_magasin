{{-- Une ligne de la saisie d'une sortie. $i : index (ou __I__ dans le gabarit JS) ; $line : valeurs ; $article : article choisi. --}}
@php
    $err = fn (string $field) => is_int($i) && $errors->has("lines.$i.$field");
@endphp
<tr class="sortie-line" data-stock="{{ $article ? (float) $article->stock_quantity : '' }}" data-unite="{{ $article?->unite?->symbol }}">
    <td>
        <select class="form-select line-article" name="lines[{{ $i }}][article_id]" aria-label="Article">
            @if ($article)
                <option value="{{ $article->id }}" selected>{{ $article->label() }}</option>
            @endif
        </select>
        @if ($err('article_id'))<div class="text-danger small mt-1">{{ $errors->first("lines.$i.article_id") }}</div>@endif
    </td>
    <td class="line-stock fw-semibold text-nowrap">{{ $article ? $article->stockForDisplay().' '.$article->unite?->symbol : '—' }}</td>
    <td>
        <input type="text" inputmode="decimal" class="form-control form-control-sm line-quantity @if ($err('quantity')) is-invalid @endif" name="lines[{{ $i }}][quantity]" value="{{ $line['quantity'] ?? '' }}">
        @if ($err('quantity'))<div class="invalid-feedback">{{ $errors->first("lines.$i.quantity") }}</div>@endif
    </td>
    <td class="line-after text-nowrap">—</td>
    <td>
        <input type="text" class="form-control form-control-sm" name="lines[{{ $i }}][observation]" value="{{ $line['observation'] ?? '' }}" maxlength="500">
    </td>
    <td>
        <button type="button" class="btn btn-primary btn-sm line-remove" title="Retirer la ligne"><i class='bx bx-trash'></i></button>
    </td>
</tr>
