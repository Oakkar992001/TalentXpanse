<?php

namespace App\Services;

use App\Models\User;

class MarketplaceTwoFactorService
{
    public function newSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function qrUri(User $user, string $secret): string
    {
        return 'otpauth://totp/'.rawurlencode(config('app.name', 'TalentXpanse').':'.$user->email).'?'.http_build_query([
            'secret' => $secret,
            'issuer' => config('app.name', 'TalentXpanse'),
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ]);
    }

    public function verify(User $user, ?string $code): bool
    {
        $code = preg_replace('/\s+/', '', (string) $code);
        if ($code === '') {
            return false;
        }
        if (strlen($code) >= 8 && $this->consumeRecoveryCode($user, $code)) {
            return true;
        }
        if (! $user->two_factor_secret || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        foreach ([-1, 0, 1] as $offset) {
            if (hash_equals($this->totp($user->two_factor_secret, intdiv(time(), 30) + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function recoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn () => strtoupper(substr(bin2hex(random_bytes(5)), 0, 5).'-'.substr(bin2hex(random_bytes(5)), 0, 5)))->all();
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?: [];
        $index = collect($codes)->search(fn ($hash) => password_verify($code, $hash));
        if ($index === false) {
            return false;
        }
        unset($codes[$index]);
        $user->update(['two_factor_recovery_codes' => array_values($codes)]);

        return true;
    }

    private function totp(string $secret, int $counter): string
    {
        $hash = hash_hmac('sha1', pack('N2', 0, $counter), $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($value) as $character) {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    private function base32Decode(string $value): string
    {
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $bits = '';
        foreach (str_split(strtoupper($value)) as $character) {
            if (! isset($alphabet[$character])) {
                continue;
            }
            $bits .= str_pad(decbin($alphabet[$character]), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
