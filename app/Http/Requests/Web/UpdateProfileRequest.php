<?php

namespace App\Http\Requests\Web;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification de ses propres informations : nom et numéro de téléphone seulement. Rôle, institution et statut ne se
 * changent jamais ici. Le numéro est l'identifiant de connexion (D26) : le changer exige le mot de passe actuel.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::E164_PATTERN, Rule::unique('users', 'phone')->ignore($this->user()->getKey())],
            'phone_password' => [Rule::requiredIf(fn () => $this->phoneChanged()), 'nullable', 'string', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Le numéro de téléphone n\'est pas valide (exemple : 70 00 00 01).',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé par un autre compte.',
            'phone_password.required' => 'Saisissez votre mot de passe actuel pour changer de numéro.',
            'phone_password.current_password' => 'Le mot de passe actuel est incorrect.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nom', 'phone' => 'numéro de téléphone', 'phone_password' => 'mot de passe actuel'];
    }

    protected function prepareForValidation(): void
    {
        $phone = trim((string) $this->input('phone'));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'phone' => PhoneNumber::normalize($phone) ?? $phone,
        ]);
    }

    private function phoneChanged(): bool
    {
        return $this->input('phone') !== $this->user()->phone;
    }
}
