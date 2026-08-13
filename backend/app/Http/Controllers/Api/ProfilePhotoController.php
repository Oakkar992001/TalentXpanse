<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\MarketplaceStorage;
use App\Services\MarketplaceUploadSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoController extends Controller
{
    public function store(Request $request, MarketplaceUploadSafetyService $safety)
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $safety->inspect($request->file('photo'), 'profile_photo');
        $path = $request->file('photo')->store("profile-photos/{$request->user()->id}", MarketplaceStorage::publicDisk());
        $oldPath = $request->user()->profile_photo_path;
        $request->user()->update(['profile_photo_path' => $path]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk(MarketplaceStorage::publicDisk())->delete($oldPath);
        }

        return ['data' => ['profile_photo_url' => $request->user()->fresh()->profile_photo_url]];
    }
}
