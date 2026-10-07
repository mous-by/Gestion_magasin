<?php

namespace App\Http\Requests\Web\Referentiels;

use App\Models\Service;
use Illuminate\Validation\Rule;

class ServiceRequest extends ReferentielRequest
{
    protected function routeKey(): string
    {
        return 'service';
    }

    protected function modelClass(): string
    {
        return Service::class;
    }

    protected function nullableFields(): array
    {
        return ['code'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('services', 'name')->ignore($this->model())],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('services', 'code')->ignore($this->model())],
        ] + $this->statusRule();
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Un service porte déjà ce nom (éventuellement supprimé).',
            'code.unique' => 'Ce code est déjà utilisé par un autre service.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nom', 'code' => 'code'];
    }
}
