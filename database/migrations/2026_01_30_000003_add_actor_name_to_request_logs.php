<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('request_logs', 'actor_name')) {
            Schema::table('request_logs', function (Blueprint $table) {
                $table->string('actor_name', 120)->nullable()->after('request_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('request_logs', 'actor_name')) {
            Schema::table('request_logs', function (Blueprint $table) {
                $table->dropColumn('actor_name');
            });
        }
    }
};
