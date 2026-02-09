<?php

namespace App\Listeners;

use App\Events\PurchaseRequestCreated;
use App\Jobs\SendPurchaseRequestNotification;
use Illuminate\Support\Facades\Log;

class SendPurchaseRequestReceipt
{
    public function handle(PurchaseRequestCreated $event): void
    {
        $emailTo = trim((string) $event->purchaseRequest->account_email);
        
        if ($emailTo === '') {
            Log::info('Skipping receipt email - no email provided', [
                'request_id' => $event->purchaseRequest->id,
                'code' => $event->purchaseRequest->code,
            ]);
            return;
        }

        Log::info('Dispatching receipt notification job (sync)', [
            'request_id' => $event->purchaseRequest->id,
            'code' => $event->purchaseRequest->code,
            'email' => $emailTo,
        ]);

        try {
            SendPurchaseRequestNotification::dispatchSync(
                $event->purchaseRequest->id,
                $event->charges,
                $event->storeName
            );
        } catch (\Throwable $exception) {
            Log::error('Receipt email failed to send (sync)', [
                'request_id' => $event->purchaseRequest->id,
                'code' => $event->purchaseRequest->code,
                'email' => $emailTo,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
