<?php

namespace App\Http\Requests\Facturacion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReciboRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente' => ['required', 'string', 'max:255'],
            'casillero' => ['required', 'string', 'max:255'],
            'email_cliente' => ['required', 'email', 'max:255'],
            'sucursal' => ['required', 'string', 'max:50'],
            'fecha' => ['required', 'date'],
            'metodo_pago' => [
                'required',
                'string',
                Rule::in(['Efectivo', 'Tarjeta', 'Transferencia', 'Yappy']),
            ],
            'tipo_servicio' => ['nullable', 'string', Rule::in(['BASIC', 'OTRO'])],
            'link_producto' => ['nullable', 'url', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['nullable', 'string', 'max:255'],
            'items.*.precio' => ['required', 'numeric', 'min:0'],
        ];
    }
}
