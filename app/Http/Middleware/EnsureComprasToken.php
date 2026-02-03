<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureComprasToken
{
    public function handle(Request $request, Closure $next)
    {
        $expected = trim((string) config('services.compras.admin_token', ''));

        if ($expected === '') {
            if (app()->environment('local')) {
                return $next($request);
            }
            return response()->json([
                'success' => false,
                'data' => null,
                'errors' => ['message' => ['Token de compras no configurado']],
            ], 503);
        }

        $got = (string) $request->header('x-compras-token', '');
        if ($got !== '' && hash_equals($expected, $got)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'data' => null,
            'errors' => ['message' => ['No autorizado']],
        ], 401);
    }
}
