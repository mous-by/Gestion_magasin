<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\ActivityLogger;
use App\Services\System\SystemStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Page Système : état de l'installation en lecture seule (`system.view`) et quelques actions de maintenance sur liste
 * blanche (`system.maintain`), toutes journalisées. Permissions réservées au superadmin.
 */
class SystemController extends Controller
{
    public function index(SystemStatus $status): View
    {
        return view('system.index', [
            'environment' => $status->environment(),
            'database' => $status->database(),
            'queue' => $status->queue(),
            'tasks' => SystemStatus::TASKS,
        ]);
    }

    public function maintain(string $task, ActivityLogger $logger): RedirectResponse
    {
        $definition = SystemStatus::TASKS[$task] ?? abort(404);

        Artisan::call($definition['command']);

        $logger->log($definition['audit'], 'system', $definition['label'].' (page Système)', null, [], [], null, null, $definition['label']);

        return redirect()->route('system.index')->with('status', $definition['label'].' : terminé.');
    }
}
