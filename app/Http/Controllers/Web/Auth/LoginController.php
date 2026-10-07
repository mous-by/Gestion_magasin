<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LoginRequest;
use App\Services\Audit\ActivityLogger;
use App\Services\Auth\CredentialChecker;
use App\Support\LoginSlides;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(LoginSlides $slides): View
    {
        return view('auth.login', ['slides' => $slides->all()]);
    }

    public function store(LoginRequest $request, CredentialChecker $checker): RedirectResponse
    {
        $user = $checker->attempt($request->validated('phone'), $request->validated('password'), 'web', (string) $request->ip());

        // « Se souvenir de moi » n'est pas exposé (CLAUDE.md §4) : la session dure 120 minutes (D13).
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $checker->succeeded($user, 'web');

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $request->user();

        $logger->log('auth.logout', 'auth', "Déconnexion — {$user->name}", $user, actor: $user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
