<?php

namespace App\Http\Requests\Web\Referentiels;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base commune des formulaires des référentiels : la même requête sert à la création (pas de modèle dans la
 * route) et à la modification (modèle lié). Le statut actif / inactif ne se règle qu'en modification.
 */
abstract class ReferentielRequest extends FormRequest
{
    /** Nom du paramètre de route qui porte le modèle (« article », « categorie »…). */
    abstract protected function routeKey(): string;

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** Champs texte facultatifs : une saisie vide devient null. */
    protected function nullableFields(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        $model = $this->model();

        return $model !== null
            ? ($this->user()?->can('update', $model) ?? false)
            : ($this->user()?->can('create', $this->modelClass()) ?? false);
    }

    public function model(): ?Model
    {
        $model = $this->route($this->routeKey());

        return $model instanceof Model ? $model : null;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach ($this->all() as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $data[$key] = $value === '' && in_array($key, $this->nullableFields(), true) ? null : $value;
            }
        }

        if ($this->model() !== null) {
            $data['is_active'] = $this->boolean('is_active');
        }

        $this->merge($data);
    }

    /**
     * Règle du statut, en modification seulement.
     *
     * @return array<string, mixed>
     */
    protected function statusRule(): array
    {
        return $this->model() !== null ? ['is_active' => ['boolean']] : [];
    }
}
