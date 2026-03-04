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
     */
    public function handle(Request $request, Closure $next, ...$roles): Response // <-- Perhatikan ...$roles
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Cek apakah user memiliki salah satu role yang diizinkan
        if (! in_array($request->user()->role, $roles)) {
            return response()->json(['message' => 'Anda tidak memiliki akses sebagai ' . implode(', ', $roles)], 403);
        }

        return $next($request);
    }
}
