<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('original_name', 255);
            $table->string('stored_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedInteger('size_bytes');
            $table->timestamp('uploaded_at')->useCurrent();

            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_attachments');
    }
};

