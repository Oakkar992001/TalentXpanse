<?php

namespace App\Support;

final class MarketplaceStorage
{
    public static function privateDisk(): string
    {
        return config('marketplace_storage.private_disk', 'local');
    }

    public static function publicDisk(): string
    {
        return config('marketplace_storage.public_disk', 'public');
    }
}
