<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index()
    {
        $total = (int) PurchaseRequest::query()->count();
        $pending = (int) PurchaseRequest::query()
            ->whereIn('status', ['Borrador', 'Pendiente comprobante'])
            ->count();
        $sent = (int) PurchaseRequest::query()->where('status', 'Enviada al Supervisor')->count();
        $completed = (int) PurchaseRequest::query()->whereIn('status', ['Compra realizada', 'Completada'])->count();

        $recent = DB::table('purchase_requests as pr')
            ->join('stores as s', 's.id', '=', 'pr.store_id')
            ->select([
                'pr.id',
                'pr.code',
                'pr.client_name',
                'pr.client_code',
                'pr.status',
                'pr.updated_at',
                DB::raw(
                    "CASE
                        WHEN pr.store_custom_name IS NOT NULL AND pr.store_custom_name <> ''
                          THEN s.name || ' - ' || pr.store_custom_name
                        ELSE s.name
                     END AS store_name",
                ),
            ])
            ->orderByDesc('pr.updated_at')
            ->limit(5)
            ->get();

        return response()->json([
            'kpis' => [
                'total' => $total,
                'pending' => $pending,
                'sentToSupervisor' => $sent,
                'completed' => $completed,
            ],
            'recentActivity' => $recent,
        ]);
    }
}
