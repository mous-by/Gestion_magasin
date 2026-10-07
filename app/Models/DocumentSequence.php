<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Compteur technique de numérotation (RG10), manipulé uniquement par DocumentNumberService. Pas d'audit propre :
 * le numéro attribué figure dans l'audit du document validé.
 */
#[Fillable(['prefix', 'year', 'last_number'])]
class DocumentSequence extends Model {}
