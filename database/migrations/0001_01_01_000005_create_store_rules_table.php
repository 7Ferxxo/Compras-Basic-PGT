<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_rules', function (Blueprint $table) {
            $table->foreignId('store_id')->primary()->constrained('stores')->cascadeOnDelete();
            $table->boolean('requires_residential_address')->default(false);
            $table->decimal('residential_fee_per_item', 10, 2)->default(2.00);
            $table->boolean('requires_american_card')->default(false);
            $table->decimal('american_card_surcharge_rate', 10, 4)->default(0.03);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_rules');
    }
};

