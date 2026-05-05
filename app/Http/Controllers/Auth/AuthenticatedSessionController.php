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
        $rolDesc = strtolower($user->rol?->descripcion ?? '');
        $rolId = (int)$user->id_rol;

        // PRIORIDAD 1: Cocina (Busca coincidencia parcial o ID 3)
        if (str_contains($rolDesc, 'cocina') || str_contains($rolDesc, 'cocinero') || $rolId === 3) {
            return redirect('/cocina');
        }

        // PRIORIDAD 2: Mesero (Busca coincidencia o ID 2)
        if (str_contains($rolDesc, 'mesero') || $rolId === 2) {
            return redirect('/mesero');
        }

        // PRIORIDAD 3: Admin (Busca coincidencia o ID 1)
        if (str_contains($rolDesc, 'admin') || $rolId === 1) {
            return redirect('/admin');
        }

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
