<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();

            return response()->json([
                'ok' => true,
                'service' => 'api',
                'time' => now()->toISOString(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'code' => 'db_unreachable',
                'message' => 'No hay conexion a la base de datos',
            ], 503);
        }
    }
}

