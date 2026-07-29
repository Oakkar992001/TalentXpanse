<?php

return [
    'currency' => env('MARKETPLACE_PAYMENT_CURRENCY', 'MMK'),
    'platform_fee_basis_points' => (int) env('MARKETPLACE_PLATFORM_FEE_BASIS_POINTS', 1000),
    'enabled' => (bool) env('MARKETPLACE_PAYMENTS_ENABLED', false),
    'provider' => env('MARKETPLACE_PAYMENT_PROVIDER'),
];
