<?php

namespace App\Http\Requests\Web\Referentiels;

use App\Models\Category;
use Illuminate\Validation\Rule;

class CategoryRequest extends ReferentielRequest
{
    protected function routeKey(): string
    {
        return 'category';
    }

    protected function modelClass(): string
    {
        return Category::class;
    }

    protected function nullableFields(): array
    {
        return ['description'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($this->model())],
            'description' => ['nullable', 'string', 'max:255'],
        ] + $this->statusRule();
    }

    public function messages(): array
    {
        return ['name.unique' => 'Une catégorie porte déjà ce nom (éventuellement supprimée).'];
    }

    public function attributes(): array
    {
        return ['name' => 'nom', 'description' => 'description'];
    }
}
