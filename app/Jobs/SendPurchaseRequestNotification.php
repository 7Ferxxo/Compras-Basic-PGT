<?php

namespace App\Jobs;

use App\Models\PurchaseRequest;
use App\Models\RequestLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPurchaseRequestNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 120, 300];

    public function __construct(
        public int $purchaseRequestId,
        public array $charges,
        public string $storeName,
        public bool $force = false
    ) {
    }

    private function configurePdfRuntime(): void
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '0');
        @ini_set('max_input_time', '0');
        @set_time_limit(0);
    }

    public function handle(): void
    {
        $this->configurePdfRuntime();

        $purchaseRequest = PurchaseRequest::query()->find($this->purchaseRequestId);

        if (!$purchaseRequest) {
            Log::error('Purchase request not found for receipt notification', [
                'request_id' => $this->purchaseRequestId,
            ]);
            return;
        }

        $emailTo = trim((string) $purchaseRequest->account_email);

        if ($emailTo === '') {
            Log::warning('No email address for receipt notification', [
                'request_id' => $purchaseRequest->id,
                'code' => $purchaseRequest->code,
            ]);
            return;
        }

        if (!$this->force && $purchaseRequest->receipt_sent_at) {
            Log::info('Skipping receipt email - already sent', [
                'request_id' => $purchaseRequest->id,
                'code' => $purchaseRequest->code,
                'email' => $emailTo,
            ]);
            return;
        }

        $lock = Cache::lock('purchase-request-receipt:' . $purchaseRequest->id, 60);
        if (!$lock->get()) {
            Log::info('Skipping receipt email - lock already held', [
                'request_id' => $purchaseRequest->id,
                'code' => $purchaseRequest->code,
                'email' => $emailTo,
            ]);
            return;
        }

        $purchaseRequest->increment('receipt_send_attempts');

        $pdfBytes = null;
        $pdfError = null;

        try {
            $pdf = Pdf::loadView('pdf.purchase-request', [
                'request' => $purchaseRequest,
                'storeName' => $this->storeName,
                'charges' => $this->charges,
            ]);

            $pdfBytes = $pdf->output();
        } catch (\Throwable $e) {
            $pdfError = $e->getMessage();
            Log::warning('PDF generation failed, sending email without attachment', [
                'request_id' => $purchaseRequest->id,
                'code' => $purchaseRequest->code,
                'error' => $pdfError,
            ]);
        }

        try {
            Mail::send('emails.purchase-request', [
                'request' => $purchaseRequest,
                'storeName' => $this->storeName,
                'charges' => $this->charges,
                'logoUrl' => 'https://www.pgtlogistics.com/assetsAuth/img/logoNew.png',
            ], function ($message) use ($emailTo, $purchaseRequest, $pdfBytes) {
                $subject = 'Nueva Solicitud de Compra - ' . $purchaseRequest->code;
                $message->to($emailTo)->subject($subject);
                if ($pdfBytes !== null) {
                    $message->attachData($pdfBytes, $purchaseRequest->code . '.pdf');
                }
            });

            $purchaseRequest->update([
                'receipt_sent_at' => now(),
                'receipt_send_error' => $pdfError,
            ]);

            RequestLog::query()->create([
                'request_id' => $purchaseRequest->id,
                'action' => 'receipt_sent',
                'from_status' => $purchaseRequest->status,
                'to_status' => $purchaseRequest->status,
                'note' => $pdfBytes === null
                    ? "Correo enviado sin PDF adjunto por error de render: {$pdfError}"
                    : "Comprobante enviado a {$emailTo}",
                'actor_name' => 'system',
            ]);

            Log::info('Receipt notification sent successfully', [
                'request_id' => $purchaseRequest->id,
                'code' => $purchaseRequest->code,
                'email' => $emailTo,
                'attempt' => $purchaseRequest->receipt_send_attempts,
                'with_pdf' => $pdfBytes !== null,
            ]);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();

            $purchaseRequest->update([
                'receipt_send_error' => $errorMessage,
            ]);

            RequestLog::query()->create([
                'request_id' => $purchaseRequest->id,
                'action' => 'receipt_send_failed',
                'from_status' => $purchaseRequest->status,
                'to_status' => $purchaseRequest->status,
                'note' => "Error enviando comprobante: {$errorMessage}",
                'actor_name' => 'system',
            ]);

            Log::error('Error sending receipt notification', [
                'request_id' => $purchaseRequest->id,
                'code' => $purchaseRequest->code,
                'email' => $emailTo,
                'error' => $errorMessage,
                'attempt' => $purchaseRequest->receipt_send_attempts,
            ]);

            throw $e;
        } finally {
            optional($lock)->release();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $purchaseRequest = PurchaseRequest::query()->find($this->purchaseRequestId);

        if ($purchaseRequest) {
            Log::error('Receipt notification failed permanently after all retries', [
                'request_id' => $purchaseRequest->id,
                'code' => $purchaseRequest->code,
                'attempts' => $purchaseRequest->receipt_send_attempts,
                'error' => $exception->getMessage(),
            ]);

            RequestLog::query()->create([
                'request_id' => $purchaseRequest->id,
                'action' => 'receipt_send_failed_permanently',
                'from_status' => $purchaseRequest->status,
                'to_status' => $purchaseRequest->status,
                'note' => "Fallo permanente despues de {$purchaseRequest->receipt_send_attempts} intentos",
                'actor_name' => 'system',
            ]);
        }
    }
}
