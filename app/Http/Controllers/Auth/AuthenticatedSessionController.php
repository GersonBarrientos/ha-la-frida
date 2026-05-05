<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = \Illuminate\Support\Facades\Auth::user();
        $user->load('rol');
        $rol = strtolower($user->rol?->descripcion ?? '');

        if (in_array($rol, ['cocinero', 'cocina'])) {
            return redirect('/cocina');
        } elseif ($rol === 'mesero') {
            return redirect('/mesero');
        } elseif ($rol === 'admin') {
            return redirect('/admin');
        }

        // Respaldo por ID solo si no hay descripción
        if ($user->id_rol == 3) return redirect('/cocina');
        if ($user->id_rol == 2) return redirect('/mesero');
        if ($user->id_rol == 1) return redirect('/admin');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
