<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres du journal d'audit. L'autorisation est portée par la route (permission `audit.view`).
 */
class AuditFilterRequest extends FormRequest
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
            'author' => ['nullable', 'string', 'max:150'],
            'module' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', 'string', 'max:100'],
            'channel' => ['nullable', 'in:web,console'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'superadmin' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'from.date' => 'La date de début n\'est pas valide.',
            'to.date' => 'La date de fin n\'est pas valide.',
            'to.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'channel.in' => 'Canal inconnu.',
        ];
    }
}
