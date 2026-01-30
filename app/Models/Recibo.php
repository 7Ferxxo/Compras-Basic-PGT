<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recibo extends Model
{
    use HasFactory;

    protected $table = 'recibos';

    public $timestamps = false;

    protected $hidden = [
        'pdf_blob',
    ];

    protected $fillable = [
        'cliente',
        'casillero',
        'sucursal',
        'monto',
        'concepto',
        'metodo_pago',
        'fecha',
        'email_cliente',
        'pdf_filename',
        'pdf_blob'
    ];
}
