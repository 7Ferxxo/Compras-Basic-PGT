<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'sent_to_supervisor' => 'Enviada al Supervisor',
            'completed' => 'Completada',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'cancelled' => 'Cancelada',
            default => $status ?: 'Pendiente',
        };
    }

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
            ->where('status', 'pending')
            ->count();
        $sent = (int) PurchaseRequest::query()->where('status', 'sent_to_supervisor')->count();
        $completed = (int) PurchaseRequest::query()->where('status', 'completed')->count();

        $storeNameExpr = $this->storeNameExpression();
        $recent = DB::table('request_logs as rl')
            ->join('purchase_requests as pr', 'pr.id', '=', 'rl.request_id')
            ->join('stores as s', 's.id', '=', 'pr.store_id')
            ->select([
                'pr.id',
                'pr.code',
                'pr.client_name',
                'pr.client_code',
                'pr.status',
                'rl.action',
                'rl.actor_name',
                'rl.created_at',
                DB::raw($storeNameExpr),
            ])
            ->orderByDesc('rl.created_at')
            ->limit(6)
            ->get()
            ->map(function ($row) {
                $row->status_label = $this->statusLabel($row->status ?? null);
                $row->actor_name = $row->actor_name ?: 'system';
                return $row;
            });

        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => [
                    'total' => $total,
                    'pending' => $pending,
                    'sentToSupervisor' => $sent,
                    'completed' => $completed,
                ],
                'recentActivity' => $recent,
            ],
            'errors' => null,
        ]);
    }
}
