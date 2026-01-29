<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};

