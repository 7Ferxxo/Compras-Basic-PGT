<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `recibos` ADD COLUMN `pdf_blob` LONGBLOB NULL AFTER `pdf_filename`");
            return;
        }

        Schema::table('recibos', function (Blueprint $table) {
            $table->binary('pdf_blob')->nullable()->after('pdf_filename');
        });
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `recibos` DROP COLUMN `pdf_blob`");
            return;
        }

        Schema::table('recibos', function (Blueprint $table) {
            $table->dropColumn('pdf_blob');
        });
    }
};
