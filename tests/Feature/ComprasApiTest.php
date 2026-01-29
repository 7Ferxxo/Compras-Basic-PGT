<?php

namespace Tests\Feature;

use App\Models\RequestAttachment;
use Database\Seeders\ComprasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ComprasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_compras_entrypoint_redirects(): void
    {
        $res = $this->get('/compras');
        $res->assertRedirect('/compras/pages/panel-compras/panel-compras.html');
    }

    public function test_root_redirects_to_compras(): void
    {
        $res = $this->get('/');
        $res->assertRedirect('/compras');
    }

    public function test_stores_endpoint_returns_seeded_items(): void
    {
        $this->seed(ComprasSeeder::class);

        $res = $this->getJson('/api/stores');
        $res->assertOk();
        $res->assertJsonCount(7, 'items');
    }

    public function test_create_purchase_request_and_list(): void
    {
        $this->seed(ComprasSeeder::class);

        $res = $this->post('/api/purchase-requests', [
            'clientName' => 'Cliente Demo',
            'clientCode' => 'CLI-001',
            'quotedTotal' => '50.00',
            'paymentMethod' => 'Transferencia',
            'storeId' => '2',
            'storeCustomName' => '',
            'itemLink' => 'https://amazon.com/item/123',
            'accountEmail' => '',
            'accountPassword' => '',
            'quoteScreenshots' => [
                UploadedFile::fake()->create('captura.png', 10, 'image/png'),
            ],
        ]);

        $res->assertStatus(201);
        $id = $res->json('id');
        $this->assertIsInt($id);

        $list = $this->getJson('/api/purchase-requests?page=1&pageSize=10');
        $list->assertOk();
        $this->assertGreaterThanOrEqual(1, count($list->json('items') ?? []));

        $detail = $this->getJson('/api/purchase-requests/' . $id);
        $detail->assertOk();
        $detail->assertJsonFragment(['id' => $id]);
    }

    public function test_only_compras_can_mark_compra_realizada(): void
    {
        $this->seed(ComprasSeeder::class);

        config(['services.compras.admin_token' => 'secret-token']);

        $res = $this->post('/api/purchase-requests', [
            'clientName' => 'Cliente Demo',
            'clientCode' => 'CLI-002',
            'quotedTotal' => '20.00',
            'paymentMethod' => 'Efectivo',
            'storeId' => '1',
            'itemLink' => 'https://walmart.com/item/1',
            'quoteScreenshots' => [
                UploadedFile::fake()->create('captura.png', 10, 'image/png'),
            ],
        ]);
        $res->assertStatus(201);
        $id = $res->json('id');

        $forbidden = $this->patchJson("/api/purchase-requests/{$id}/status", [
            'status' => 'Compra realizada',
            'note' => '',
        ]);
        $forbidden->assertStatus(403);

        $ok = $this->withHeader('X-Compras-Token', 'secret-token')->patchJson("/api/purchase-requests/{$id}/status", [
            'status' => 'Compra realizada',
            'note' => 'ok',
        ]);
        $ok->assertOk();
        $ok->assertJsonFragment(['status' => 'Compra realizada']);
    }

    public function test_can_attach_evidences_from_receipt_endpoint(): void
    {
        $res = $this->post("/api/purchase-requests/receipt/123/attachments", [
            'cliente' => 'Cliente Demo',
            'casillero' => 'CLI-009',
            'metodo_pago' => 'Transferencia',
            'descripcion_compra' => 'Compra test',
            'monto_pagado' => '10.00',
            'link_producto' => '',
            'quoteScreenshots' => [
                UploadedFile::fake()->create('captura-1.png', 10, 'image/png'),
                UploadedFile::fake()->create('captura-2.png', 10, 'image/png'),
            ],
            'paymentProof' => UploadedFile::fake()->create('comprobante.pdf', 20, 'application/pdf'),
        ]);

        $res->assertOk();
        $this->assertTrue((bool) $res->json('ok'));
        $requestId = (int) $res->json('request_id');
        $this->assertGreaterThan(0, $requestId);

        $this->assertSame(3, RequestAttachment::query()->where('request_id', $requestId)->count());
    }
}
