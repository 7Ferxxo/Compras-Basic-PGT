<?php

namespace Tests\Feature;

use App\Events\PurchaseRequestCreated;
use App\Jobs\SendPurchaseRequestNotification;
use App\Models\PurchaseRequest;
use App\Models\RequestLog;
use Database\Seeders\ComprasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PurchaseRequestReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ComprasSeeder::class);
    }

    public function test_creates_request_and_queues_receipt_successfully(): void
    {
        Event::fake();

        $response = $this->postJson('/api/purchase-requests', [
            'clientName' => 'Cliente Test',
            'clientCode' => 'TEST-001',
            'quotedTotal' => '100.00',
            'paymentMethod' => 'Transferencia',
            'storeId' => '2',
            'itemLink' => 'https://amazon.com/item/test',
            'accountEmail' => 'test@example.com',
            'accountPassword' => '',
            'quoteScreenshots' => [
                UploadedFile::fake()->create('screenshot.png', 10, 'image/png'),
            ],
        ]);

        $response->assertStatus(201);
        $this->assertTrue($response->json('data.receipt_queued'));

        $requestId = $response->json('data.id');
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $requestId,
            'status' => 'pending',
            'account_email' => 'test@example.com',
        ]);

        Event::assertDispatched(PurchaseRequestCreated::class, function ($event) use ($requestId) {
            return $event->purchaseRequest->id === $requestId;
        });
    }

    public function test_validation_fails_when_required_fields_missing(): void
    {
        Event::fake();
        Mail::fake();

        $response = $this->postJson('/api/purchase-requests', [
            'clientCode' => 'TEST-002',
            'quotedTotal' => '50.00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['clientName', 'quoteScreenshots']);

        $this->assertDatabaseMissing('purchase_requests', [
            'client_code' => 'TEST-002',
        ]);

        Event::assertNotDispatched(PurchaseRequestCreated::class);
    }

    public function test_handles_email_sending_failure_gracefully(): void
    {
        Event::fake();

        $response = $this->postJson('/api/purchase-requests', [
            'clientName' => 'Cliente Error',
            'clientCode' => 'ERROR-001',
            'quotedTotal' => '75.00',
            'storeId' => '1',
            'itemLink' => 'https://walmart.com/item/test',
            'accountEmail' => 'error@example.com',
            'quoteScreenshots' => [
                UploadedFile::fake()->create('screenshot.png', 10, 'image/png'),
            ],
        ]);

        $response->assertStatus(201);
        $requestId = $response->json('data.id');

        $purchaseRequest = PurchaseRequest::find($requestId);
        $this->assertNotNull($purchaseRequest);
        $this->assertEquals('error@example.com', $purchaseRequest->account_email);

        Event::assertDispatched(PurchaseRequestCreated::class);

        $purchaseRequest->update([
            'receipt_send_error' => 'SMTP connection failed',
            'receipt_send_attempts' => 1,
        ]);

        $purchaseRequest->refresh();
        $this->assertNull($purchaseRequest->receipt_sent_at);
        $this->assertNotNull($purchaseRequest->receipt_send_error);
        $this->assertStringContainsString('SMTP connection failed', $purchaseRequest->receipt_send_error);
        $this->assertGreaterThan(0, $purchaseRequest->receipt_send_attempts);
    }

    public function test_prevents_duplicate_requests_on_double_submit(): void
    {
        Event::fake();

        $requestData = [
            'clientName' => 'Cliente Doble',
            'clientCode' => 'DOBLE-001',
            'quotedTotal' => '60.00',
            'storeId' => '3',
            'itemLink' => 'https://temu.com/item/unique',
            'accountEmail' => 'doble@example.com',
            'quoteScreenshots' => [
                UploadedFile::fake()->create('screenshot.png', 10, 'image/png'),
            ],
        ];

        $response1 = $this->postJson('/api/purchase-requests', $requestData);
        $response1->assertStatus(201);
        $id1 = $response1->json('data.id');

        $requestData['quoteScreenshots'] = [
            UploadedFile::fake()->create('screenshot2.png', 10, 'image/png'),
        ];
        $response2 = $this->postJson('/api/purchase-requests', $requestData);
        $response2->assertStatus(201);
        $id2 = $response2->json('data.id');

        $this->assertNotEquals($id1, $id2);

        Event::assertDispatched(PurchaseRequestCreated::class, 2);
    }

    public function test_can_resend_receipt_after_failure(): void
    {
        Event::fake();

        $response = $this->postJson('/api/purchase-requests', [
            'clientName' => 'Cliente Reenvio',
            'clientCode' => 'RESEND-001',
            'quotedTotal' => '80.00',
            'storeId' => '2',
            'itemLink' => 'https://amazon.com/item/resend',
            'accountEmail' => 'resend@example.com',
            'quoteScreenshots' => [
                UploadedFile::fake()->create('screenshot.png', 10, 'image/png'),
            ],
        ]);

        $requestId = $response->json('data.id');

        $purchaseRequest = PurchaseRequest::find($requestId);
        $purchaseRequest->update([
            'receipt_send_error' => 'Initial failure',
            'receipt_send_attempts' => 1,
        ]);

        $resendResponse = $this->postJson("/api/purchase-requests/{$requestId}/resend-receipt");
        $resendResponse->assertOk();
        $resendResponse->assertJson([
            'success' => true,
            'data' => [
                'ok' => true,
                'email' => 'resend@example.com',
            ],
        ]);

        $this->assertDatabaseHas('request_logs', [
            'request_id' => $requestId,
            'action' => 'receipt_resend_requested',
        ]);
    }

    public function test_resend_fails_when_no_email(): void
    {
        $response = $this->postJson('/api/purchase-requests', [
            'clientName' => 'Cliente Sin Email',
            'clientCode' => 'NOEMAIL-001',
            'quotedTotal' => '40.00',
            'storeId' => '1',
            'itemLink' => 'https://walmart.com/item/test',
            'accountEmail' => '', // No email
            'quoteScreenshots' => [
                UploadedFile::fake()->create('screenshot.png', 10, 'image/png'),
            ],
        ]);

        $requestId = $response->json('data.id');

        $resendResponse = $this->postJson("/api/purchase-requests/{$requestId}/resend-receipt");
        $resendResponse->assertStatus(400);
        $resendResponse->assertJson([
            'success' => false,
        ]);
    }

    public function test_receipt_status_included_in_show_response(): void
    {
        Event::fake();
        config(['services.compras.admin_token' => 'test-token']);

        $response = $this->postJson('/api/purchase-requests', [
            'clientName' => 'Cliente Status',
            'clientCode' => 'STATUS-001',
            'quotedTotal' => '90.00',
            'storeId' => '2',
            'itemLink' => 'https://amazon.com/item/status',
            'accountEmail' => 'status@example.com',
            'quoteScreenshots' => [
                UploadedFile::fake()->create('screenshot.png', 10, 'image/png'),
            ],
        ]);

        $requestId = $response->json('data.id');

        $detailResponse = $this->withHeader('X-Compras-Token', 'test-token')
            ->getJson("/api/purchase-requests/{$requestId}");
        
        $detailResponse->assertOk();
        $detailResponse->assertJsonStructure([
            'data' => [
                'receipt_status' => [
                    'sent',
                    'sent_at',
                    'error',
                    'attempts',
                ],
            ],
        ]);

        $receiptStatus = $detailResponse->json('data.receipt_status');
        $this->assertFalse($receiptStatus['sent']);
        $this->assertNull($receiptStatus['sent_at']);
        $this->assertEquals(0, $receiptStatus['attempts']);
    }
}
