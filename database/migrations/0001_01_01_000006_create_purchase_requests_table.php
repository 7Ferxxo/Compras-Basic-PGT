<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('client_name', 120);
            $table->string('client_code', 50);
            $table->string('contact_channel', 80)->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('account_email', 255)->nullable();
            $table->text('account_password_enc')->nullable();
            $table->foreignId('store_id')->constrained('stores');
            $table->string('store_custom_name', 255)->nullable();
            $table->string('item_link', 2000);
            $table->text('item_options')->nullable();
            $table->unsignedInteger('item_quantity')->default(1);
            $table->decimal('quoted_total', 10, 2);
            $table->decimal('residential_charge', 10, 2)->default(0);
            $table->decimal('american_card_charge', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 50);
            $table->string('sent_note', 1000)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('source_system', 50)->nullable();
            $table->string('source_reference', 100)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('store_id');
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};

