<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MarketplaceUploadSafetyService
{
    private const DANGEROUS_EXTENSIONS = ['ade', 'adp', 'apk', 'app', 'bat', 'cmd', 'com', 'cpl', 'dll', 'exe', 'hta', 'jar', 'js', 'jse', 'lnk', 'msi', 'msp', 'pif', 'ps1', 'scr', 'sh', 'vb', 'vbe', 'vbs', 'wsf'];

    public function inspect(UploadedFile $file, string $context): void
    {
        $name = $file->getClientOriginalName();
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $mime = strtolower((string) $file->getMimeType());

        if (str_contains($name, "\0") || in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['file' => 'This file type is not allowed.']);
        }
        if ($mime === 'application/x-dosexec' || str_starts_with($mime, 'application/x-executable')) {
            throw ValidationException::withMessages(['file' => 'This file type is not allowed.']);
        }

        $driver = config('marketplace_security.upload_scan_driver', 'metadata');
        if ($driver === 'clamav') {
            $this->scanWithClamAv($file, $context);
        }
    }

    private function scanWithClamAv(UploadedFile $file, string $context): void
    {
        $host = config('marketplace_security.clamav_host');
        $port = (int) config('marketplace_security.clamav_port');
        $socket = @fsockopen($host, $port, $errorNumber, $errorMessage, 5);
        if (! $socket) {
            Log::warning('Marketplace upload scan service unavailable.', ['context' => $context, 'error' => $errorMessage]);
            throw ValidationException::withMessages(['file' => 'File scanning is temporarily unavailable. Please try again shortly.']);
        }

        try {
            fwrite($socket, "zINSTREAM\0");
            $stream = fopen($file->getRealPath(), 'rb');
            while (! feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk !== false && $chunk !== '') {
                    fwrite($socket, pack('N', strlen($chunk)).$chunk);
                }
            }
            fclose($stream);
            fwrite($socket, pack('N', 0));
            $response = stream_get_contents($socket) ?: '';
        } finally {
            fclose($socket);
        }

        if (str_contains($response, 'FOUND')) {
            Log::warning('Marketplace upload rejected by antivirus scanner.', ['context' => $context]);
            throw ValidationException::withMessages(['file' => 'This file failed the marketplace safety scan.']);
        }
        if (! str_contains($response, 'OK')) {
            Log::warning('Marketplace upload scan gave an unexpected response.', ['context' => $context]);
            throw ValidationException::withMessages(['file' => 'File scanning could not be completed. Please try again.']);
        }
    }
}
