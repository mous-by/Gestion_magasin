<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\PlannedModules;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fiche d'un module métier prévu mais non implémenté (D25) : rôle du cahier des charges et points ouverts.
 * Aucune donnée, aucune permission : 404 si le module n'est pas visible par le rôle de l'utilisateur.
 */
class PlannedModuleController extends Controller
{
    public function __invoke(Request $request, string $module, PlannedModules $planned): View
    {
        $definition = $planned->find($module, $request->user());

        abort_if($definition === null, 404);

        return view('modules.show', ['key' => $module, 'module' => $definition]);
    }
}
