<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientProfile;
use App\Models\FreelancerProfile;
use App\Models\OauthIdentity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', 'in:client,freelancer'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $this->attachRole($user, $data['role']);

            return $user;
        });
        $user->sendEmailVerificationNotification();

        return response()->json([
            'token' => $user->createToken('talentxpanse-web')->plainTextToken,
            'user' => $this->userPayload($user->fresh('roles', 'freelancerProfile', 'clientProfile')),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'The email or password is incorrect.'], 422);
        }
        if ($user->status === 'suspended') {
            return response()->json(['message' => 'This account has been suspended.'], 403);
        }

        return response()->json([
            'token' => $user->createToken('talentxpanse-web')->plainTextToken,
            'user' => $this->userPayload($user->load('roles', 'freelancerProfile', 'clientProfile')),
        ]);
    }

    public function adminLogin(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $user = User::where('email', $data['email'])->with('roles')->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->hasRole('admin')) {
            return response()->json(['message' => 'Administrator credentials are not valid.'], 422);
        }
        if ($user->status === 'suspended') {
            return response()->json(['message' => 'This administrator account has been suspended.'], 403);
        }

        return response()->json([
            'token' => $user->createToken('talentxpanse-admin', ['admin'])->plainTextToken,
            'user' => $this->userPayload($user->load('freelancerProfile', 'clientProfile')),
        ]);
    }

    public function google(Request $request)
    {
        $data = $request->validate([
            'credential' => ['required', 'string'],
            'role' => ['nullable', 'in:client,freelancer'],
        ]);
        $claims = $this->verifyGoogleCredential($data['credential']);

        $user = DB::transaction(function () use ($claims, $data) {
            $identity = OauthIdentity::where('provider', 'google')->where('provider_id', $claims['sub'])->first();

            if ($identity) {
                return $identity->user;
            }

            $user = User::where('email', $claims['email'])->first();

            if (! $user) {
                $user = User::create([
                    'name' => ($claims['name'] ?? null) ?: Str::before($claims['email'], '@'),
                    'email' => $claims['email'],
                    'email_verified_at' => now(),
                    'password' => Str::random(64),
                ]);
                $this->attachRole($user, $data['role'] ?? 'freelancer');
            }

            OauthIdentity::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => $claims['sub']]);

            return $user;
        });
        abort_if($user->status === 'suspended', 403, 'This account has been suspended.');

        return response()->json([
            'token' => $user->createToken('talentxpanse-google')->plainTextToken,
            'user' => $this->userPayload($user->fresh('roles', 'freelancerProfile', 'clientProfile')),
        ]);
    }

    public function user(Request $request)
    {
        return ['user' => $this->userPayload($request->user()->load('roles', 'freelancerProfile', 'clientProfile'))];
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function addRole(Request $request)
    {
        $data = $request->validate(['role' => ['required', 'in:client,freelancer']]);
        $this->attachRole($request->user(), $data['role']);
        $request->user()->update(['active_role' => $data['role']]);

        return ['user' => $this->userPayload($request->user()->fresh('roles', 'freelancerProfile', 'clientProfile'))];
    }

    public function setActiveRole(Request $request)
    {
        $role = $request->validate(['role' => ['required', 'in:client,freelancer']])['role'];
        abort_unless($request->user()->hasRole($role), 403, 'That workspace is not enabled on this account.');
        $request->user()->update(['active_role' => $role]);

        return ['user' => $this->userPayload($request->user()->fresh('roles', 'freelancerProfile', 'clientProfile'))];
    }

    private function attachRole(User $user, string $roleName): void
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        if ($roleName === 'freelancer') {
            FreelancerProfile::firstOrCreate(['user_id' => $user->id]);
        } else {
            ClientProfile::firstOrCreate(['user_id' => $user->id]);
        }

        if (! $user->active_role) {
            $user->update(['active_role' => $roleName]);
        }
    }

    private function userPayload(User $user): array
    {
        $roles = $user->roles->pluck('name')->values();
        $activeRole = $roles->contains($user->active_role) ? $user->active_role : $roles->first();
        if ($activeRole && $user->active_role !== $activeRole) {
            $user->update(['active_role' => $activeRole]);
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_photo_url' => $user->profile_photo_url,
            'roles' => $roles,
            'active_role' => $activeRole,
            'email_verified' => filled($user->email_verified_at),
            'account_status' => $user->status,
            'freelancer_profile' => $user->freelancerProfile,
            'client_profile' => $user->clientProfile,
        ];
    }

    private function verifyGoogleCredential(string $credential): array
    {
        $parts = explode('.', $credential);
        if (count($parts) !== 3) {
            throw ValidationException::withMessages(['credential' => 'Google returned an invalid sign-in token.']);
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        $claims = json_decode($this->base64UrlDecode($encodedPayload), true);
        $signature = $this->base64UrlDecode($encodedSignature);

        if (! is_array($header) || ! is_array($claims) || ($header['alg'] ?? null) !== 'RS256' || empty($header['kid'])) {
            throw ValidationException::withMessages(['credential' => 'Google returned an invalid sign-in token.']);
        }

        $key = $this->googleKeys()->firstWhere('kid', $header['kid']);
        if (! $key) {
            Cache::forget('google-id-token-keys');
            $key = $this->googleKeys()->firstWhere('kid', $header['kid']);
        }

        if (! $key || openssl_verify("{$encodedHeader}.{$encodedPayload}", $signature, $this->jwkToPem($key), OPENSSL_ALGO_SHA256) !== 1) {
            throw ValidationException::withMessages(['credential' => 'Google could not verify this sign-in token.']);
        }

        $audience = config('services.google.client_id');
        $audiences = is_array($claims['aud'] ?? null) ? $claims['aud'] : [$claims['aud'] ?? null];
        $isVerifiedEmail = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            || ! in_array($audience, $audiences, true)
            || ($claims['exp'] ?? 0) < time()
            || empty($claims['sub'])
            || empty($claims['email'])
            || ! $isVerifiedEmail) {
            throw ValidationException::withMessages(['credential' => 'Google returned a token that is not valid for TalentXpanse.']);
        }

        return $claims;
    }

    private function googleKeys()
    {
        return collect(Cache::remember('google-id-token-keys', now()->addHour(), function () {
            return Http::timeout(5)->acceptJson()->get('https://www.googleapis.com/oauth2/v3/certs')->throw()->json('keys');
        }));
    }

    private function jwkToPem(array $key): string
    {
        $rsaKey = $this->derSequence(
            $this->derInteger($this->base64UrlDecode($key['n']))
            .$this->derInteger($this->base64UrlDecode($key['e']))
        );
        $algorithm = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $subjectPublicKey = "\x03".$this->derLength(strlen($rsaKey) + 1)."\x00".$rsaKey;
        $pem = $this->derSequence($algorithm.$subjectPublicKey);

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($pem), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private function derSequence(string $value): string
    {
        return "\x30".$this->derLength(strlen($value)).$value;
    }

    private function derInteger(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value === '' || ord($value[0]) > 127) {
            $value = "\x00".$value;
        }

        return "\x02".$this->derLength(strlen($value)).$value;
    }

    private function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(128 | strlen($bytes)).$bytes;
    }

    private function base64UrlDecode(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;
        if ($padding) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($value, true) ?: '';
    }
}
