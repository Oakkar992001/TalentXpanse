<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\MarketplaceNotificationService;
use App\Services\MarketplaceOfferService;
use App\Services\MarketplaceSavedSearchAlertService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('marketplace:send-job-alerts', function (MarketplaceSavedSearchAlertService $alerts, MarketplaceNotificationService $notifications) {
    $sent = $alerts->sendDueAlerts($notifications);
    $this->info("Sent {$sent} saved-search job alerts.");
})->purpose('Send daily saved-search job alerts');

Schedule::command('marketplace:send-job-alerts')->dailyAt('08:00')->withoutOverlapping();

Artisan::command('marketplace:expire-offers', function (MarketplaceOfferService $offers, MarketplaceNotificationService $notifications) {
    $expired = $offers->expireDueOffers($notifications);
    $this->info("Expired {$expired} pending offer(s).");
})->purpose('Expire formal offers that were not accepted in time');

Schedule::command('marketplace:expire-offers')->hourly()->withoutOverlapping();
