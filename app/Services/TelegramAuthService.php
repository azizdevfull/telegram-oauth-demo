<?php
namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Exception;

class TelegramAuthService
{
    private const string AUTH_URL  = 'https://oauth.telegram.org/auth';
    private const string TOKEN_URL = 'https://oauth.telegram.org/token';
    private const string JWKS_URL  = 'https://oauth.telegram.org/jwks';

    public function redirect()
    {
        $state        = Str::random(40);
        $codeVerifier = Str::random(128);

        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        Session::put('telegram_auth_state',    $state);
        Session::put('telegram_code_verifier', $codeVerifier);

        $query = http_build_query([
            'client_id'             => config('services.telegram.client_id'),
            'redirect_uri'          => url(config('services.telegram.redirect_uri')),
            'response_type'         => 'code',
            'scope'                 => 'openid profile phone telegram:bot_access',
            'state'                 => $state,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away(self::AUTH_URL . '?' . $query);
    }

    public function handleCallback(array $requestData): array
    {
        $savedState = Session::pull('telegram_auth_state');
        if (empty($savedState) || $savedState !== $requestData['state']) {
            throw new Exception('Invalid state parameter.');
        }

        $clientId     = config('services.telegram.client_id');
        $clientSecret = config('services.telegram.client_secret');
        $credentials  = base64_encode("$clientId:$clientSecret");

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $credentials,
        ])->asForm()->post(self::TOKEN_URL, [
            'grant_type'    => 'authorization_code',
            'code'          => $requestData['code'],
            'redirect_uri'  => url(config('services.telegram.redirect_uri')),
            'client_id'     => $clientId,
            'code_verifier' => Session::pull('telegram_code_verifier'),
        ]);

        if ($response->failed()) {
            throw new Exception('Failed to fetch Telegram token: ' . $response->body());
        }

        $tokens = $response->json();

        $payload = $this->verifyIdToken($tokens['id_token']);

        return [
            'tokens' => $tokens,
            'user'   => $payload,
        ];
    }

    /**
     * JWT tokenni Telegram JWKS orqali kriptografik tekshirish.
     * Kalitlar 1 soat keshlanadi — har so'rovda tarmoq chaqiruvi bo'lmaydi.
     */
    private function verifyIdToken(string $idToken): array
    {
        $jwks = Cache::remember('telegram_jwks', 3600, function () {
            $response = Http::get(self::JWKS_URL);

            if ($response->failed()) {
                throw new Exception('Failed to fetch Telegram JWKS: ' . $response->body());
            }

            return $response->json();
        });

        $keys = JWK::parseKeySet($jwks);

        $decoded = JWT::decode($idToken, $keys);

        $payload = (array) $decoded;

        if (($payload['iss'] ?? '') !== 'https://oauth.telegram.org') {
            throw new Exception('Invalid token issuer.');
        }

        if (($payload['aud'] ?? '') !== config('services.telegram.client_id')) {
            throw new Exception('Invalid token audience.');
        }

        return $payload;
    }
}
