<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un paramètre modifiable depuis l'interface (clé / valeur). Lu et écrit par App\Support\DocumentSettings.
 * Chaque modification est auditée (ancienne et nouvelle valeur).
 */
#[Fillable(['key', 'value'])]
class AppSetting extends Model
{
    use LogsActivity;

    public function activityModule(): string
    {
        return 'documents';
    }

    public function activityNoun(): string
    {
        return 'Paramètre';
    }

    public function activityLabel(): string
    {
        return (string) $this->key;
    }
}
