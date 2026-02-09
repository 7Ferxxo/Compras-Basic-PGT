<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->timestamp('receipt_sent_at')->nullable()->after('pdf_blob');
            $table->text('receipt_send_error')->nullable()->after('receipt_sent_at');
            $table->unsignedInteger('receipt_send_attempts')->default(0)->after('receipt_send_error');

            $table->index('receipt_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->dropIndex(['receipt_sent_at']);
            $table->dropColumn(['receipt_sent_at', 'receipt_send_error', 'receipt_send_attempts']);
        });
    }
};
