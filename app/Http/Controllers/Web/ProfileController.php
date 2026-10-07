<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ChangePasswordRequest;
use App\Http\Requests\Web\UpdateProfileRequest;
use App\Services\Users\UserProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('roles');

        return view('profile.show', ['user' => $user]);
    }

    public function update(UpdateProfileRequest $request, UserProvisioningService $users): RedirectResponse
    {
        $changed = $users->updateProfile($request->user(), $request->validated());

        return redirect()->route('profile.show')
            ->with('status', $changed ? 'Vos informations ont été mises à jour.' : 'Aucune modification.')
            ->with('tab', 'info');
    }

    public function updatePassword(ChangePasswordRequest $request, UserProvisioningService $users): RedirectResponse
    {
        $users->changePassword($request->user(), $request->validated('password'), $request->session()->getId());

        return redirect()->route('profile.show')->with('status', 'Mot de passe modifié avec succès.')->with('tab', 'password');
    }
}
