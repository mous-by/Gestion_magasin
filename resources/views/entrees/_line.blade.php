{{-- Une ligne de la saisie d'une entrée. $i : index (ou __I__ dans le gabarit JS) ; $line : valeurs ; $article : article choisi. --}}
@php
    $err = fn (string $field) => is_int($i) && $errors->has("lines.$i.$field");
@endphp
<tr class="entree-line" data-decimal="{{ $article?->unite?->allows_decimal ? 1 : 0 }}">
    <td>
        <select class="form-select line-article" name="lines[{{ $i }}][article_id]" aria-label="Article">
            @if ($article)
                <option value="{{ $article->id }}" selected>{{ $article->label() }}</option>
            @endif
        </select>
        @if ($err('article_id'))<div class="text-danger small mt-1">{{ $errors->first("lines.$i.article_id") }}</div>@endif
        <div class="line-flags mt-1"></div>
    </td>
    <td class="small text-muted line-stock text-nowrap">{{ $article ? $article->stockForDisplay().' '.$article->unite?->symbol : '—' }}</td>
    @foreach (['expected_qty' => 'line-expected', 'delivered_qty' => 'line-delivered', 'accepted_qty' => 'line-accepted'] as $field => $class)
        <td>
            <input type="text" inputmode="decimal" class="form-control form-control-sm {{ $class }} @if ($err($field)) is-invalid @endif"
                   name="lines[{{ $i }}][{{ $field }}]" value="{{ $line[$field] ?? '' }}" @if ($field === 'accepted_qty' && filled($line[$field] ?? null)) data-touched="1" @endif>
            @if ($err($field))<div class="invalid-feedback">{{ $errors->first("lines.$i.$field") }}</div>@endif
        </td>
    @endforeach
    <td class="line-rejected text-nowrap">—</td>
    <td>
        <select class="form-select form-select-sm line-reason @if ($err('rejection_reason')) is-invalid @endif" name="lines[{{ $i }}][rejection_reason]" aria-label="Motif du rejet">
            <option value="">—</option>
            @foreach (\App\Enums\RejectionReason::cases() as $reason)
                <option value="{{ $reason->value }}" @selected(($line['rejection_reason'] ?? null) === $reason->value)>{{ $reason->label() }}</option>
            @endforeach
        </select>
        @if ($err('rejection_reason'))<div class="invalid-feedback">{{ $errors->first("lines.$i.rejection_reason") }}</div>@endif
    </td>
    <td>
        <input type="text" class="form-control form-control-sm @if ($err('observation')) is-invalid @endif" name="lines[{{ $i }}][observation]" value="{{ $line['observation'] ?? '' }}" maxlength="500" placeholder="Obligatoire en cas d'écart">
        @if ($err('observation'))<div class="invalid-feedback">{{ $errors->first("lines.$i.observation") }}</div>@endif
    </td>
    <td>
        <button type="button" class="btn btn-primary btn-sm line-remove" title="Retirer la ligne"><i class='bx bx-trash'></i></button>
    </td>
</tr>
