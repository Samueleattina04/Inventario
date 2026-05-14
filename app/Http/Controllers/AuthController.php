<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect(Auth::user()->isAdmin() ? route('admin.dashboard') : route('location'));
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if (Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']])) {
            $user = Auth::user();

            if (! $user->active) {
                Auth::logout();
                return back()->withErrors(['username' => 'Account disattivato. Contattare l\'amministratore.']);
            }

            $request->session()->regenerate();

            return redirect($user->isAdmin() ? route('admin.dashboard') : route('location'));
        }

        return back()->withErrors([
            'username' => 'Credenziali non valide.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
