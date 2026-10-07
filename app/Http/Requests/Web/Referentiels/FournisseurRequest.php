<?php

namespace App\Http\Requests\Web\Referentiels;

use App\Models\Fournisseur;
use Illuminate\Validation\Rule;

/**
 * Fiche fournisseur (cahier §10). Le téléphone reste libre (numéros étrangers, fixes, plusieurs numéros).
 */
class FournisseurRequest extends ReferentielRequest
{
    protected function routeKey(): string
    {
        return 'fournisseur';
    }

    protected function modelClass(): string
    {
        return Fournisseur::class;
    }

    protected function nullableFields(): array
    {
        return ['address', 'phone', 'email', 'contact_person', 'admin_references'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('fournisseurs', 'name')->ignore($this->model())],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\/\s-]+$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'admin_references' => ['nullable', 'string', 'max:255'],
        ] + $this->statusRule();
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Un fournisseur porte déjà ce nom (éventuellement supprimé).',
            'phone.regex' => 'Le téléphone ne doit contenir que des chiffres, espaces et les signes + - . / ( ).',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom ou raison sociale', 'address' => 'adresse', 'phone' => 'téléphone', 'email' => 'e-mail',
            'contact_person' => 'personne contact', 'admin_references' => 'références administratives',
        ];
    }
}
