<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Cargar relación de rol si no está cargada
        if (!$user->relationLoaded('rol')) {
            $user->load('rol');
        }

        // Verificar si el id_rol del usuario está en los roles permitidos
        if (!in_array($user->id_rol, $roles, true)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'No tienes permisos para acceder a este recurso. Se requiere rol: ' . implode(', ', $roles)
            ], 403);
        }

        return $next($request);
    }
}
