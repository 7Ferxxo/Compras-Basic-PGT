<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    private function storeNameExpression(): string
    {
        $driver = DB::connection()->getDriverName();
        $concat = $driver === 'sqlite'
            ? "s.name || ' - ' || pr.store_custom_name"
            : "CONCAT(s.name, ' - ', pr.store_custom_name)";

        return "CASE
                    WHEN pr.store_custom_name IS NOT NULL AND pr.store_custom_name <> ''
                      THEN {$concat}
                    ELSE s.name
                END AS store_name";
    }

    public function index()
    {
        $total = (int) PurchaseRequest::query()->count();
        $pending = (int) PurchaseRequest::query()
            ->whereIn('status', ['Borrador', 'Pendiente comprobante'])
            ->count();
        $sent = (int) PurchaseRequest::query()->where('status', 'Enviada al Supervisor')->count();
        $completed = (int) PurchaseRequest::query()->whereIn('status', ['Compra realizada', 'Completada'])->count();

        $storeNameExpr = $this->storeNameExpression();
        $recent = DB::table('purchase_requests as pr')
            ->join('stores as s', 's.id', '=', 'pr.store_id')
            ->select([
                'pr.id',
                'pr.code',
                'pr.client_name',
                'pr.client_code',
                'pr.status',
                'pr.updated_at',
                DB::raw($storeNameExpr),
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
