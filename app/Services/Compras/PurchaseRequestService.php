<?php

namespace App\Services\Compras;

use App\Models\PurchaseRequest;
use App\Models\RequestLog;
use App\Models\Store;
use App\Models\StoreRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PurchaseRequestService
{
    public function ensureDefaultStoresExist(): void
    {
        $stores = [
            1 => 'WALMART',
            2 => 'AMAZON',
            3 => 'TEMU',
            4 => 'SHEIN',
            5 => 'EBAY',
            6 => 'ALIEXPRESS',
            7 => 'OTROS',
        ];

        foreach ($stores as $id => $name) {
            Store::updateOrCreate(['id' => $id], ['name' => $name]);
        }

        $rules = [
            1 => ['requires_residential_address' => true, 'residential_fee_per_item' => 2.00, 'requires_american_card' => false, 'american_card_surcharge_rate' => 0.03],
            2 => ['requires_residential_address' => false, 'residential_fee_per_item' => 2.00, 'requires_american_card' => true, 'american_card_surcharge_rate' => 0.03],
            3 => ['requires_residential_address' => false, 'residential_fee_per_item' => 2.00, 'requires_american_card' => false, 'american_card_surcharge_rate' => 0.03],
            4 => ['requires_residential_address' => false, 'residential_fee_per_item' => 2.00, 'requires_american_card' => false, 'american_card_surcharge_rate' => 0.03],
            5 => ['requires_residential_address' => false, 'residential_fee_per_item' => 2.00, 'requires_american_card' => false, 'american_card_surcharge_rate' => 0.03],
            6 => ['requires_residential_address' => false, 'residential_fee_per_item' => 2.00, 'requires_american_card' => false, 'american_card_surcharge_rate' => 0.03],
            7 => ['requires_residential_address' => false, 'residential_fee_per_item' => 2.00, 'requires_american_card' => false, 'american_card_surcharge_rate' => 0.03],
        ];

        foreach ($rules as $storeId => $data) {
            StoreRule::updateOrCreate(['store_id' => $storeId], ['store_id' => $storeId, ...$data]);
        }
    }

       
                                                                         
       
    public function computeCharges(int $storeId, int $itemQuantity, float $quotedTotal): array
    {
        $rules = StoreRule::query()->where('store_id', $storeId)->first();

        $residentialCharge = $rules?->requires_residential_address
            ? (float) $rules->residential_fee_per_item * max(1, $itemQuantity)
            : 0.0;

        $americanCardCharge = $rules?->requires_american_card
            ? $quotedTotal * (float) ($rules->american_card_surcharge_rate ?? 0.03)
            : 0.0;

        return [
            'residentialCharge' => (float) number_format($residentialCharge, 2, '.', ''),
            'americanCardCharge' => (float) number_format($americanCardCharge, 2, '.', ''),
        ];
    }

    public function nextCode(): string
    {
        $nextId = ((int) PurchaseRequest::query()->max('id')) + 1;
        return 'BASIC-' . str_pad((string) $nextId, 3, '0', STR_PAD_LEFT);
    }

       
                                                            
       
    public function pickStoreFromItemLink(?string $itemLink): array
    {
        $raw = trim((string) ($itemLink ?? ''));
        if ($raw === '') return ['storeId' => 7, 'storeCustomName' => 'OTROS'];

        try {
            $u = parse_url($raw);
            $host = strtolower((string) ($u['host'] ?? ''));
            $host = preg_replace('/^www\./', '', $host);

            if (str_contains($host, 'walmart')) return ['storeId' => 1, 'storeCustomName' => null];
            if (str_contains($host, 'amazon')) return ['storeId' => 2, 'storeCustomName' => null];
            if (str_contains($host, 'temu')) return ['storeId' => 3, 'storeCustomName' => null];
            if (str_contains($host, 'shein')) return ['storeId' => 4, 'storeCustomName' => null];
            if (str_contains($host, 'ebay')) return ['storeId' => 5, 'storeCustomName' => null];
            if (str_contains($host, 'aliexpress') || str_contains($host, 'ali-express')) {
                return ['storeId' => 6, 'storeCustomName' => null];
            }

            $custom = trim(substr($host, 0, 255));
            return ['storeId' => 7, 'storeCustomName' => $custom !== '' ? $custom : 'OTROS'];
        } catch (\Throwable) {
            return ['storeId' => 7, 'storeCustomName' => 'OTROS'];
        }
    }

       
                                                                                                    
       
    public function storeUpload(UploadedFile $file): array
    {
        $ext = $file->getClientOriginalExtension();
        $ext = $ext ? '.' . substr($ext, 0, 10) : '';

        $dir = 'purchase-requests';
        $filename = Str::lower(Str::random(32)) . $ext;
        $storedName = $dir . '/' . $filename;
        Storage::disk('public')->putFileAs($dir, $file, $filename);

        return [
            'stored_name' => $storedName,
            'original_name' => (string) $file->getClientOriginalName(),
            'mime_type' => (string) ($file->getClientMimeType() ?: 'application/octet-stream'),
            'size_bytes' => (int) $file->getSize(),
        ];
    }

    public function createFromInvoiceWebhookPayload(array $payload): PurchaseRequest
    {
        $receiptId = trim((string) ($payload['id_recibo'] ?? ''));
        $clientCode = trim((string) ($payload['casillero'] ?? ''));
        $clientName = trim((string) ($payload['cliente'] ?? ''));
        $itemLink = trim((string) ($payload['link_producto'] ?? ''));

        if ($receiptId === '') {
            throw new \InvalidArgumentException('id_recibo es requerido');
        }
        if ($clientCode === '') {
            throw new \InvalidArgumentException('casillero es requerido');
        }

        $sourceSystem = trim((string) ($payload['origen'] ?? '')) ?: 'FACTURADOR_LARAVEL';
        $descripcion = trim((string) ($payload['descripcion_compra'] ?? '')) ?: null;
        $paymentMethod = trim((string) ($payload['metodo_pago'] ?? '')) ?: null;
        $subtotal = is_numeric($payload['subtotal'] ?? null) ? (float) $payload['subtotal'] : null;
        $montoPagado = is_numeric($payload['monto_pagado'] ?? null) ? (float) $payload['monto_pagado'] : null;
        $evidenciaUrl = trim((string) ($payload['evidencia_pago_url'] ?? '')) ?: null;

        $clientName = $clientName !== '' ? $clientName : "Casillero {$clientCode}";

        $picked = $this->pickStoreFromItemLink($itemLink);
        $storeId = (int) $picked['storeId'];
        $storeCustomName = $picked['storeCustomName'];

        $this->ensureDefaultStoresExist();

        $store = Store::query()->find($storeId);
        if (!$store) {
            throw new \InvalidArgumentException('storeId derivado invalido');
        }

        $quotedTotal = $subtotal ?? $montoPagado ?? 0.0;
        $charges = $this->computeCharges($storeId, 1, $quotedTotal);

        $meta = array_values(array_filter([
            "Origen: {$sourceSystem}",
            "Recibo: {$receiptId}",
            $montoPagado !== null ? 'Monto pagado: B/. ' . number_format($montoPagado, 2, '.', '') : null,
            $evidenciaUrl ? "PDF: {$evidenciaUrl}" : null,
        ]));

        $notes = trim(implode("\n", array_filter([
            $descripcion,
            $meta ? implode(' | ', $meta) : null,
        ]))) ?: null;

        $code = $this->nextCode();
        $status = 'pending';

        $request = PurchaseRequest::query()->create([
            'code' => $code,
            'client_name' => $clientName,
            'client_code' => $clientCode,
            'contact_channel' => 'Facturador',
            'payment_method' => $paymentMethod,
            'account_email' => null,
            'account_password_enc' => null,
            'store_id' => $storeId,
            'store_custom_name' => $storeId === 7 ? ($storeCustomName ?: 'OTROS') : null,
            'item_link' => $itemLink,
            'item_options' => $descripcion,
            'item_quantity' => 1,
            'quoted_total' => $quotedTotal,
            'residential_charge' => $charges['residentialCharge'],
            'american_card_charge' => $charges['americanCardCharge'],
            'notes' => $notes,
            'status' => $status,
            'sent_note' => null,
            'sent_at' => null,
            'source_system' => $sourceSystem,
            'source_reference' => $receiptId,
        ]);

        RequestLog::query()->create([
            'request_id' => $request->id,
            'action' => 'created_from_invoice',
            'from_status' => null,
            'to_status' => $status,
            'note' => "Creado desde Facturador (recibo {$receiptId})",
            'actor_name' => 'system',
        ]);

        return $request;
    }
}
