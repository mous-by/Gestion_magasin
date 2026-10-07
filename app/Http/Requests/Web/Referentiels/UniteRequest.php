<?php

namespace App\Http\Requests\Web\Referentiels;

use App\Models\Unite;
use Illuminate\Validation\Rule;

class UniteRequest extends ReferentielRequest
{
    protected function routeKey(): string
    {
        return 'unite';
    }

    protected function modelClass(): string
    {
        return Unite::class;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge(['allows_decimal' => $this->boolean('allows_decimal')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('unites', 'name')->ignore($this->model())],
            'symbol' => ['required', 'string', 'max:15', Rule::unique('unites', 'symbol')->ignore($this->model())],
            'allows_decimal' => ['boolean'],
        ] + $this->statusRule();
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Une unité porte déjà ce nom (éventuellement supprimée).',
            'symbol.unique' => 'Ce symbole est déjà utilisé par une autre unité.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nom', 'symbol' => 'symbole', 'allows_decimal' => 'quantités décimales'];
    }
}
