<?php

namespace Tests\Feature;

use App\Jobs\SendReceiptNotifications;
use App\Models\Recibo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReceiptNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_receipt_notification_for_non_basic_service(): void
    {
        Queue::fake();

        $response = $this->withoutMiddleware()->postJson('/crear-factura', [
            'cliente' => 'Cliente Demo',
            'casillero' => 'CAS-001',
            'email_cliente' => 'cliente@example.com',
            'sucursal' => 'Panama',
            'fecha' => now()->toDateString(),
            'metodo_pago' => 'Transferencia',
            'tipo_servicio' => 'OTRO',
            'items' => [
                [
                    'descripcion' => 'Servicio',
                    'precio' => 10,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('email_queued', true);

        Queue::assertPushed(SendReceiptNotifications::class, function (SendReceiptNotifications $job) {
            return ($job->webhookMeta['tipo_servicio'] ?? null) === 'OTRO';
        });
    }

    public function test_job_marks_receipt_as_sent_when_mail_succeeds(): void
    {
        Mail::shouldReceive('send')->once()->andReturnNull();

        $recibo = Recibo::query()->create([
            'cliente' => 'Cliente Mail',
            'casillero' => 'CAS-002',
            'sucursal' => 'Panama',
            'monto' => 25.50,
            'concepto' => 'Item test',
            'metodo_pago' => 'Transferencia',
            'fecha' => now()->toDateString(),
            'email_cliente' => 'mail@example.com',
            'pdf_filename' => null,
            'pdf_blob' => '%PDF-1.4 fake',
        ]);

        (new SendReceiptNotifications($recibo->id))->handle();

        $recibo->refresh();
        $this->assertNotNull($recibo->receipt_sent_at);
        $this->assertNull($recibo->receipt_send_error);
        $this->assertEquals(1, $recibo->receipt_send_attempts);
    }

    public function test_job_updates_error_and_rethrows_when_mail_fails(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP down'));

        $recibo = Recibo::query()->create([
            'cliente' => 'Cliente Fail',
            'casillero' => 'CAS-003',
            'sucursal' => 'Panama',
            'monto' => 30.00,
            'concepto' => 'Item test',
            'metodo_pago' => 'Transferencia',
            'fecha' => now()->toDateString(),
            'email_cliente' => 'fail@example.com',
            'pdf_filename' => null,
            'pdf_blob' => '%PDF-1.4 fake',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SMTP down');

        try {
            (new SendReceiptNotifications($recibo->id))->handle();
        } finally {
            $recibo->refresh();
            $this->assertNull($recibo->receipt_sent_at);
            $this->assertEquals(1, $recibo->receipt_send_attempts);
            $this->assertStringContainsString('SMTP down', (string) $recibo->receipt_send_error);
        }
    }

    public function test_job_is_idempotent_when_receipt_was_already_sent(): void
    {
        Mail::shouldReceive('send')->never();

        $recibo = Recibo::query()->create([
            'cliente' => 'Cliente Idempotente',
            'casillero' => 'CAS-004',
            'sucursal' => 'Panama',
            'monto' => 40.00,
            'concepto' => 'Item test',
            'metodo_pago' => 'Transferencia',
            'fecha' => now()->toDateString(),
            'email_cliente' => 'ok@example.com',
            'pdf_filename' => null,
            'pdf_blob' => '%PDF-1.4 fake',
            'receipt_sent_at' => now(),
            'receipt_send_attempts' => 2,
        ]);

        (new SendReceiptNotifications($recibo->id))->handle();

        $recibo->refresh();
        $this->assertEquals(2, $recibo->receipt_send_attempts);
    }
}
