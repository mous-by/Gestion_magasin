<?php

namespace App\Http\Requests\Web;

use App\Enums\RejectionReason;
use App\Models\Article;
use App\Models\Entree;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Saisie d'une entrée (cahier §11, §12) : en-tête et lignes. Règles des écarts (RG08) : la quantité acceptée ne
 * dépasse pas la livrée, une quantité rejetée a un motif, et tout écart (manquant, excédent, rejet, article non
 * prévu) exige une observation sur la ligne. `action` = `draft` (enregistrer) ou `validate` (enregistrer et valider).
 */
class EntreeRequest extends FormRequest
{
    private const QUANTITIES = ['expected_qty', 'delivered_qty', 'accepted_qty'];

    public function authorize(): bool
    {
        $entree = $this->route('entree');
        $user = $this->user();

        $allowed = $entree instanceof Entree ? ($user?->can('update', $entree) ?? false) : ($user?->can('create', Entree::class) ?? false);

        return $allowed && ($this->input('action') !== 'validate' || $user->can('entrees.validate'));
    }

    protected function prepareForValidation(): void
    {
        $lines = [];

        foreach ((array) $this->input('lines', []) as $line) {
            if (! is_array($line) || blank($line['article_id'] ?? null)) {
                continue; // ligne vide laissée par le formulaire
            }
            foreach (self::QUANTITIES as $field) {
                $value = trim((string) ($line[$field] ?? ''));
                $line[$field] = $value === '' ? null : str_replace([',', ' '], ['.', ''], $value);
            }
            $line['observation'] = filled($line['observation'] ?? null) ? trim($line['observation']) : null;
            $line['rejection_reason'] = filled($line['rejection_reason'] ?? null) ? $line['rejection_reason'] : null;
            $lines[] = $line;
        }

        $this->merge([
            'lines' => $lines,
            'delivery_note_number' => $this->filled('delivery_note_number') ? trim($this->input('delivery_note_number')) : null,
            'order_reference' => $this->filled('order_reference') ? trim($this->input('order_reference')) : null,
            'observation' => $this->filled('observation') ? trim($this->input('observation')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $entree = $this->route('entree');
        $currentFournisseur = $entree instanceof Entree ? $entree->fournisseur_id : null;

        return [
            'action' => ['required', Rule::in(['draft', 'validate'])],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'fournisseur_id' => ['required', 'integer', Rule::exists('fournisseurs', 'id')->whereNull('deleted_at')
                ->where(fn ($q) => $q->where('is_active', true)->when($currentFournisseur, fn ($q) => $q->orWhere('id', $currentFournisseur)))],
            'delivery_note_number' => ['nullable', 'string', 'max:60'],
            'order_reference' => ['nullable', 'string', 'max:60'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            // RG07 : article actif seulement (le brouillon d'un article désactivé se corrige).
            'lines.*.article_id' => ['required', 'integer', 'distinct', Rule::exists('articles', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'lines.*.expected_qty' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.delivered_qty' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.accepted_qty' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.rejection_reason' => ['nullable', Rule::enum(RejectionReason::class)],
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
            $planned = collect($lines)->contains(fn ($line) => $line['expected_qty'] !== null);
            $articles = Article::with('unite')->whereIn('id', collect($lines)->pluck('article_id'))->get()->keyBy('id');

            foreach ($lines as $i => $line) {
                $article = $articles[(int) $line['article_id']] ?? null;
                $delivered = (float) $line['delivered_qty'];
                $accepted = (float) $line['accepted_qty'];
                $name = $article?->reference ?? 'ligne '.($i + 1);

                if ($accepted > $delivered) {
                    $validator->errors()->add("lines.$i.accepted_qty", "{$name} : la quantité acceptée ne peut pas dépasser la quantité livrée.");
                }
                if ($accepted < $delivered && $line['rejection_reason'] === null) {
                    $validator->errors()->add("lines.$i.rejection_reason", "{$name} : indiquez le motif de la quantité non acceptée.");
                }

                if ($article && ! $article->unite?->allows_decimal) {
                    foreach (self::QUANTITIES as $field) {
                        if ($line[$field] !== null && floor((float) $line[$field]) != (float) $line[$field]) {
                            $validator->errors()->add("lines.$i.$field", "{$name} : l'unité « {$article->unite?->name} » n'admet pas de quantité décimale.");
                        }
                    }
                }

                // RG08 : tout écart est documenté.
                $gap = $line['expected_qty'] === null ? null : $delivered - (float) $line['expected_qty'];
                $hasGap = ($gap !== null && abs($gap) > 0.0005) || $accepted < $delivered || ($planned && $line['expected_qty'] === null);
                if ($hasGap && $line['observation'] === null) {
                    $validator->errors()->add("lines.$i.observation", "{$name} : un écart (manquant, excédent, rejet ou article non prévu) doit être expliqué dans l'observation.");
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
            'lines.*.delivered_qty.required' => 'Indiquez la quantité livrée.',
            'lines.*.accepted_qty.required' => 'Indiquez la quantité acceptée.',
            'received_on.before_or_equal' => 'La date de réception ne peut pas être dans le futur.',
            'fournisseur_id.exists' => 'Choisissez un fournisseur actif.',
        ];
    }

    public function attributes(): array
    {
        return [
            'received_on' => 'date de réception', 'fournisseur_id' => 'fournisseur', 'delivery_note_number' => 'numéro du bordereau',
            'order_reference' => 'référence de commande', 'observation' => 'observation générale',
            'lines.*.expected_qty' => 'quantité prévue', 'lines.*.delivered_qty' => 'quantité livrée', 'lines.*.accepted_qty' => 'quantité acceptée',
            'lines.*.rejection_reason' => 'motif', 'lines.*.observation' => 'observation',
        ];
    }

    /**
     * Données prêtes pour EntreeService::saveDraft().
     *
     * @return array<string, mixed>
     */
    public function entreeData(): array
    {
        $data = $this->validated();
        // Un motif de rejet n'a de sens que s'il y a une quantité rejetée.
        $data['lines'] = array_map(function (array $line) {
            if ((float) $line['accepted_qty'] >= (float) $line['delivered_qty']) {
                $line['rejection_reason'] = null;
            }

            return $line;
        }, $data['lines']);

        return $data;
    }
}
