<?php

namespace App\Http\Requests\Web;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation minimale : le rôle (format), le reste (nom, numéro, plafond de rôle) est validé et autorisé par
 * UserProvisioningService::create, source unique de cette logique (CLAUDE.md §4).
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return ['role' => 'rôle'];
    }
}
