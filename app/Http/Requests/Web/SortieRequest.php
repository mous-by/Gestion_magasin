<?php

namespace App\Http\Requests\Web;

use App\Models\Article;
use App\Models\Sortie;
use App\Support\Quantity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Saisie d'une sortie (cahier §13). À la validation (`action` = `validate`), chaque quantité est comparée au stock
 * disponible pour un message précis par ligne (RG04) ; SortieService refait le contrôle sous verrou.
 */
class SortieRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sortie = $this->route('sortie');
        $user = $this->user();

        $allowed = $sortie instanceof Sortie ? ($user?->can('update', $sortie) ?? false) : ($user?->can('create', Sortie::class) ?? false);

        return $allowed && ($this->input('action') !== 'validate' || $user->can('sorties.validate'));
    }

    protected function prepareForValidation(): void
    {
        $lines = [];

        foreach ((array) $this->input('lines', []) as $line) {
            if (! is_array($line) || blank($line['article_id'] ?? null)) {
                continue;
            }
            $quantity = trim((string) ($line['quantity'] ?? ''));
            $line['quantity'] = $quantity === '' ? null : str_replace([',', ' '], ['.', ''], $quantity);
            $line['observation'] = filled($line['observation'] ?? null) ? trim($line['observation']) : null;
            $lines[] = $line;
        }

        $trim = fn (string $key) => $this->filled($key) ? trim((string) $this->input($key)) : null;

        $this->merge([
            'lines' => $lines,
            'requester_name' => $trim('requester_name'),
            'reason' => $trim('reason'),
            'paper_reference' => $trim('paper_reference'),
            'observation' => $trim('observation'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sortie = $this->route('sortie');
        $currentService = $sortie instanceof Sortie ? $sortie->service_id : null;

        return [
            'action' => ['required', Rule::in(['draft', 'validate'])],
            'issued_on' => ['required', 'date', 'before_or_equal:today'],
            'requester_name' => ['required', 'string', 'max:150'],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->whereNull('deleted_at')
                ->where(fn ($q) => $q->where('is_active', true)->when($currentService, fn ($q) => $q->orWhere('id', $currentService)))],
            'reason' => ['required', 'string', 'max:255'],
            'paper_reference' => ['nullable', 'string', 'max:60'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.article_id' => ['required', 'integer', 'distinct', Rule::exists('articles', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.observation' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $lines = $this->input('lines', []);
            $articles = Article::with('unite')->whereIn('id', collect($lines)->pluck('article_id'))->get()->keyBy('id');

            foreach ($lines as $i => $line) {
                $article = $articles[(int) $line['article_id']] ?? null;
                if ($article === null) {
                    continue;
                }

                if (! $article->unite?->allows_decimal && floor((float) $line['quantity']) != (float) $line['quantity']) {
                    $validator->errors()->add("lines.$i.quantity", "{$article->reference} : l'unité « {$article->unite?->name} » n'admet pas de quantité décimale.");
                }

                if ($this->input('action') === 'validate' && (float) $line['quantity'] > (float) $article->stock_quantity) {
                    $validator->errors()->add("lines.$i.quantity", "{$article->reference} : stock insuffisant (disponible ".Quantity::format($article->stock_quantity).', demandé '.Quantity::format($line['quantity']).').');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'Ajoutez au moins un article.',
            'lines.min' => 'Ajoutez au moins un article.',
            'lines.*.article_id.distinct' => 'Un même article apparaît deux fois : regroupez les quantités sur une seule ligne.',
            'lines.*.article_id.exists' => 'Article inconnu ou inactif.',
            'lines.*.quantity.required' => 'Indiquez la quantité.',
            'lines.*.quantity.gt' => 'La quantité doit être supérieure à zéro.',
            'issued_on.before_or_equal' => 'La date de sortie ne peut pas être dans le futur.',
            'service_id.exists' => 'Choisissez un service actif.',
        ];
    }

    public function attributes(): array
    {
        return [
            'issued_on' => 'date', 'requester_name' => 'demandeur', 'service_id' => 'service bénéficiaire', 'reason' => 'motif',
            'paper_reference' => 'référence du document papier', 'observation' => 'observation',
            'lines.*.quantity' => 'quantité', 'lines.*.observation' => 'observation',
        ];
    }
}
