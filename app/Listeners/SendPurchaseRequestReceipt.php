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

        Log::info('Dispatching receipt notification job', [
            'request_id' => $event->purchaseRequest->id,
            'code' => $event->purchaseRequest->code,
            'email' => $emailTo,
        ]);

        SendPurchaseRequestNotification::dispatch(
            $event->purchaseRequest->id,
            $event->charges,
            $event->storeName
        );
    }
}
