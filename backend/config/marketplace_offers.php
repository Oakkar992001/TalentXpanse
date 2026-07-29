<?php

return [
    'expiry_days' => max(1, (int) env('MARKETPLACE_OFFER_EXPIRY_DAYS', 7)),
];
