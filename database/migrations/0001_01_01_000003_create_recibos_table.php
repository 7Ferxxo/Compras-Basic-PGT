<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos', function (Blueprint $table) {
            $table->id();
            $table->string('cliente');
            $table->string('casillero');
            $table->string('sucursal');
            $table->decimal('monto', 12, 2);
            $table->text('concepto')->nullable();
            $table->string('metodo_pago');
            $table->date('fecha');
            $table->string('email_cliente');
            $table->string('pdf_filename')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos');
    }
};

