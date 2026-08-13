<?php

return [
    // Enable only after every administrator has enrolled and stored recovery codes.
    'admin_mfa_required' => filter_var(env('MARKETPLACE_ADMIN_MFA_REQUIRED', false), FILTER_VALIDATE_BOOLEAN),
    // metadata protects extension/MIME checks; clamav fails closed when the scanner is unavailable.
    'upload_scan_driver' => env('MARKETPLACE_UPLOAD_SCAN_DRIVER', 'metadata'),
    'clamav_host' => env('CLAMAV_HOST', '127.0.0.1'),
    'clamav_port' => (int) env('CLAMAV_PORT', 3310),
];
