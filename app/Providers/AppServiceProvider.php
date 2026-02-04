<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PurchaseRequestCreated::class,
            \App\Listeners\SendPurchaseRequestReceipt::class
        );
    }
}
