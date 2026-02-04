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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPurchaseRequestNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 120, 300]; // 30s, 2min, 5min

    public function __construct(
        public int $purchaseRequestId,
        public array $charges,
        public string $storeName
    ) {
    }

    public function handle(): void
    {
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

        $purchaseRequest->increment('receipt_send_attempts');

        try {
            $pdf = Pdf::loadView('pdf.purchase-request', [
                'request' => $purchaseRequest,
                'storeName' => $this->storeName,
                'charges' => $this->charges,
            ]);

            $pdfBytes = $pdf->output();

            Mail::send('emails.purchase-request', [
                'request' => $purchaseRequest,
                'storeName' => $this->storeName,
                'charges' => $this->charges,
            ], function ($message) use ($emailTo, $purchaseRequest, $pdfBytes) {
                $subject = 'Nueva Solicitud de Compra - ' . $purchaseRequest->code;
                $message->to($emailTo)->subject($subject);
                $message->attachData($pdfBytes, $purchaseRequest->code . '.pdf');
            });

            $purchaseRequest->update([
                'receipt_sent_at' => now(),
                'receipt_send_error' => null,
            ]);

            RequestLog::query()->create([
                'request_id' => $purchaseRequest->id,
                'action' => 'receipt_sent',
                'from_status' => $purchaseRequest->status,
                'to_status' => $purchaseRequest->status,
                'note' => "Comprobante enviado a {$emailTo}",
                'actor_name' => 'system',
            ]);

            Log::info('Receipt notification sent successfully', [
                'request_id' => $purchaseRequest->id,
                'code' => $purchaseRequest->code,
                'email' => $emailTo,
                'attempt' => $purchaseRequest->receipt_send_attempts,
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
                'note' => "Fallo permanente después de {$purchaseRequest->receipt_send_attempts} intentos",
                'actor_name' => 'system',
            ]);
        }
    }
}
