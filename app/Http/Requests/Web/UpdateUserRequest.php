<?php

namespace App\Http\Requests\Web;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Autorisation seulement (plafond de rôle inclus, UserPolicy::update) : la validation des champs (nom, numéro,
 * statut) est faite par UserProvisioningService::updateByAdmin, source unique de cette logique (CLAUDE.md §4).
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
