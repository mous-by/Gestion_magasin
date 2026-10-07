<?php

namespace App\Http\Requests\Web\Referentiels;

use App\Models\Article;
use App\Models\Unite;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Fiche article (cahier §9). Référence unique en majuscules (RG01) ; catégorie et unité actives, sauf celles que
 * l'article porte déjà (désactiver une catégorie ne bloque pas la modification de ses articles).
 */
class ArticleRequest extends ReferentielRequest
{
    protected function routeKey(): string
    {
        return 'article';
    }

    protected function modelClass(): string
    {
        return Article::class;
    }

    protected function nullableFields(): array
    {
        return ['brand_model', 'min_threshold', 'location', 'observation'];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $data = ['reference' => mb_strtoupper((string) $this->input('reference'))];
        // Saisie « 2,5 » acceptée comme « 2.5 ».
        if (is_string($this->input('min_threshold'))) {
            $data['min_threshold'] = str_replace([',', ' '], ['.', ''], $this->input('min_threshold'));
        }

        $this->merge($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $article = $this->model();

        return [
            'reference' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9 ._\/-]*$/', Rule::unique('articles', 'reference')->ignore($article)],
            'designation' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', $this->activeOrCurrent('categories', $article?->category_id)],
            'unite_id' => ['required', 'integer', $this->activeOrCurrent('unites', $article?->unite_id)],
            'brand_model' => ['nullable', 'string', 'max:150'],
            'min_threshold' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'location' => ['nullable', 'string', 'max:100'],
            'observation' => ['nullable', 'string', 'max:2000'],
        ] + $this->statusRule();
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $threshold = $this->input('min_threshold');
                $unite = Unite::find($this->input('unite_id'));

                if ($threshold !== null && is_numeric($threshold) && $unite && ! $unite->allows_decimal && floor((float) $threshold) != (float) $threshold) {
                    $validator->errors()->add('min_threshold', "L'unité « {$unite->name} » n'admet pas de quantité décimale.");
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'reference.unique' => 'Cette référence est déjà utilisée (éventuellement par un article supprimé).',
            'reference.regex' => 'La référence ne contient que des lettres, chiffres, espaces et les signes . _ / -.',
            'category_id.exists' => 'Choisissez une catégorie active.',
            'unite_id.exists' => 'Choisissez une unité active.',
        ];
    }

    public function attributes(): array
    {
        return [
            'reference' => 'référence', 'designation' => 'désignation', 'category_id' => 'catégorie', 'unite_id' => 'unité',
            'brand_model' => 'marque / modèle', 'min_threshold' => 'seuil minimum', 'location' => 'emplacement', 'observation' => 'observation',
        ];
    }

    private function activeOrCurrent(string $table, ?int $current): mixed
    {
        return Rule::exists($table, 'id')->whereNull('deleted_at')
            ->where(fn ($query) => $query->where('is_active', true)->when($current, fn ($q) => $q->orWhere('id', $current)));
    }
}
