<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ChangePasswordRequest;
use App\Services\Users\UserProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Changement de mot de passe, accessible même quand le mot de passe est temporaire (D14).
 */
class PasswordChangeController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.password-change', ['forced' => $request->user()->must_change_password]);
    }

    public function update(ChangePasswordRequest $request, UserProvisioningService $users): RedirectResponse
    {
        $users->changePassword($request->user(), $request->validated('password'), $request->session()->getId());

        return redirect()->route('home')->with('status', 'Mot de passe modifié avec succès.');
    }
}
