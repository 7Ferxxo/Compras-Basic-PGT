<?php

namespace Database\Seeders;

use App\Models\PurchaseRequest;
use App\Models\RequestAttachment;
use App\Models\RequestLog;
use App\Models\Store;
use App\Models\StoreRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComprasSeeder extends Seeder
{
    public function run(): void
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

        if (PurchaseRequest::query()->exists()) {
            return;
        }

        $now = now();
        $samples = [
            [
                'client_name' => 'Maria Gomez',
                'client_code' => 'CAS-1001',
                'store_id' => 2,
                'item_link' => 'https://www.amazon.com/dp/B0TEST001',
                'quoted_total' => 129.99,
                'status' => 'pending',
            ],
            [
                'client_name' => 'Juan Perez',
                'client_code' => 'CAS-1002',
                'store_id' => 1,
                'item_link' => 'https://www.walmart.com/ip/123456',
                'quoted_total' => 54.5,
                'status' => 'sent_to_supervisor',
            ],
            [
                'client_name' => 'Ana Ruiz',
                'client_code' => 'CAS-1003',
                'store_id' => 4,
                'item_link' => 'https://www.shein.com/product/abc',
                'quoted_total' => 89.2,
                'status' => 'completed',
            ],
        ];

        foreach ($samples as $idx => $data) {
            $code = 'BASIC-' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT);
            $request = PurchaseRequest::query()->create([
                'code' => $code,
                'client_name' => $data['client_name'],
                'client_code' => $data['client_code'],
                'contact_channel' => 'Seeder',
                'payment_method' => 'Transferencia',
                'account_email' => null,
                'account_password_enc' => null,
                'store_id' => $data['store_id'],
                'store_custom_name' => null,
                'item_link' => $data['item_link'],
                'item_options' => 'Color negro, talla M',
                'item_quantity' => 1,
                'quoted_total' => $data['quoted_total'],
                'residential_charge' => 0,
                'american_card_charge' => 0,
                'notes' => 'Solicitud de demo',
                'status' => $data['status'],
                'sent_note' => null,
                'sent_at' => $data['status'] !== 'pending' ? $now : null,
                'source_system' => 'SEED',
                'source_reference' => null,
                'created_at' => $now->copy()->subDays(3 - $idx),
                'updated_at' => $now->copy()->subDays(3 - $idx),
            ]);

            $dummyPdf = "%PDF-1.4\n% demo\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
            $fileName = 'purchase-requests/' . Str::lower(Str::random(20)) . '.pdf';
            Storage::disk('public')->put($fileName, $dummyPdf);

            RequestAttachment::query()->create([
                'request_id' => $request->id,
                'type' => 'QUOTE_SCREENSHOT',
                'original_name' => 'cotizacion.pdf',
                'stored_name' => $fileName,
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($dummyPdf),
                'uploaded_at' => $now,
            ]);

            if ($data['status'] !== 'pending') {
                $proofName = 'purchase-requests/' . Str::lower(Str::random(20)) . '.pdf';
                Storage::disk('public')->put($proofName, $dummyPdf);
                RequestAttachment::query()->create([
                    'request_id' => $request->id,
                    'type' => 'PAYMENT_PROOF',
                    'original_name' => 'comprobante.pdf',
                    'stored_name' => $proofName,
                    'mime_type' => 'application/pdf',
                    'size_bytes' => strlen($dummyPdf),
                    'uploaded_at' => $now,
                ]);
            }

            RequestLog::query()->create([
                'request_id' => $request->id,
                'action' => 'created',
                'from_status' => null,
                'to_status' => 'pending',
                'note' => 'Creado por seeder',
                'created_at' => $request->created_at,
                'actor_name' => 'seed',
            ]);

            if ($data['status'] !== 'pending') {
                RequestLog::query()->create([
                    'request_id' => $request->id,
                    'action' => 'send_to_supervisor',
                    'from_status' => 'pending',
                    'to_status' => 'sent_to_supervisor',
                    'note' => 'Enviado por seeder',
                    'created_at' => $request->created_at->copy()->addHours(2),
                    'actor_name' => 'seed',
                ]);
            }

            if ($data['status'] === 'completed') {
                RequestLog::query()->create([
                    'request_id' => $request->id,
                    'action' => 'status_change',
                    'from_status' => 'sent_to_supervisor',
                    'to_status' => 'completed',
                    'note' => 'Completado por seeder',
                    'created_at' => $request->created_at->copy()->addHours(4),
                    'actor_name' => 'seed',
                ]);
            }
        }
    }
}
