<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_logs', function (Blueprint $table) {
            $table->string('actor_name', 120)->nullable()->after('request_id');
        });

        DB::table('purchase_requests')
            ->whereIn('status', ['Borrador', 'Pendiente comprobante'])
            ->update(['status' => 'pending']);
        DB::table('purchase_requests')
            ->where('status', 'Enviada al Supervisor')
            ->update(['status' => 'sent_to_supervisor']);
        DB::table('purchase_requests')
            ->whereIn('status', ['Compra realizada', 'Completada'])
            ->update(['status' => 'completed']);
        DB::table('purchase_requests')
            ->where('status', 'Cancelada')
            ->update(['status' => 'cancelled']);

        DB::table('request_logs')
            ->whereIn('from_status', ['Borrador', 'Pendiente comprobante'])
            ->update(['from_status' => 'pending']);
        DB::table('request_logs')
            ->where('from_status', 'Enviada al Supervisor')
            ->update(['from_status' => 'sent_to_supervisor']);
        DB::table('request_logs')
            ->whereIn('from_status', ['Compra realizada', 'Completada'])
            ->update(['from_status' => 'completed']);
        DB::table('request_logs')
            ->where('from_status', 'Cancelada')
            ->update(['from_status' => 'cancelled']);

        DB::table('request_logs')
            ->whereIn('to_status', ['Borrador', 'Pendiente comprobante'])
            ->update(['to_status' => 'pending']);
        DB::table('request_logs')
            ->where('to_status', 'Enviada al Supervisor')
            ->update(['to_status' => 'sent_to_supervisor']);
        DB::table('request_logs')
            ->whereIn('to_status', ['Compra realizada', 'Completada'])
            ->update(['to_status' => 'completed']);
        DB::table('request_logs')
            ->where('to_status', 'Cancelada')
            ->update(['to_status' => 'cancelled']);
    }

    public function down(): void
    {
        Schema::table('request_logs', function (Blueprint $table) {
            $table->dropColumn('actor_name');
        });

        DB::table('purchase_requests')
            ->where('status', 'pending')
            ->update(['status' => 'Pendiente comprobante']);
        DB::table('purchase_requests')
            ->where('status', 'sent_to_supervisor')
            ->update(['status' => 'Enviada al Supervisor']);
        DB::table('purchase_requests')
            ->where('status', 'completed')
            ->update(['status' => 'Completada']);
        DB::table('purchase_requests')
            ->where('status', 'cancelled')
            ->update(['status' => 'Cancelada']);

        DB::table('request_logs')
            ->where('from_status', 'pending')
            ->update(['from_status' => 'Pendiente comprobante']);
        DB::table('request_logs')
            ->where('from_status', 'sent_to_supervisor')
            ->update(['from_status' => 'Enviada al Supervisor']);
        DB::table('request_logs')
            ->where('from_status', 'completed')
            ->update(['from_status' => 'Completada']);
        DB::table('request_logs')
            ->where('from_status', 'cancelled')
            ->update(['from_status' => 'Cancelada']);

        DB::table('request_logs')
            ->where('to_status', 'pending')
            ->update(['to_status' => 'Pendiente comprobante']);
        DB::table('request_logs')
            ->where('to_status', 'sent_to_supervisor')
            ->update(['to_status' => 'Enviada al Supervisor']);
        DB::table('request_logs')
            ->where('to_status', 'completed')
            ->update(['to_status' => 'Completada']);
        DB::table('request_logs')
            ->where('to_status', 'cancelled')
            ->update(['to_status' => 'Cancelada']);
    }
};
