<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Marketplace upload disks
    |--------------------------------------------------------------------------
    |
    | Private marketplace files include CVs, conversation attachments,
    | milestone deliveries, and identity documents. They must never share a
    | publicly readable bucket with profile images.
    |
    */

    'private_disk' => env('MARKETPLACE_PRIVATE_FILESYSTEM_DISK', 'local'),

    'public_disk' => env('MARKETPLACE_PUBLIC_FILESYSTEM_DISK', 'public'),

    'external_storage_required' => env('MARKETPLACE_REQUIRE_EXTERNAL_STORAGE', false),
];
