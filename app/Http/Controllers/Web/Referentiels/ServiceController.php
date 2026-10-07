<?php

namespace App\Http\Controllers\Web\Referentiels;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Referentiels\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Écran Services bénéficiaires (phase 3) : liste, création et modification en modale, suppression en soft delete.
 */
class ServiceController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Service::class);

        return view('services.index', ['services' => Service::query()->orderBy('name')->get()]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $service = Service::create($request->validated());

        return redirect()->route('services.index')->with('status', "Service « {$service->name} » créé.");
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validated());

        return redirect()->route('services.index')->with('status', "Service « {$service->name} » modifié.");
    }

    public function destroy(Service $service): RedirectResponse
    {
        Gate::authorize('delete', $service);

        $service->delete();

        return redirect()->route('services.index')->with('status', "Service « {$service->name} » supprimé.");
    }
}
