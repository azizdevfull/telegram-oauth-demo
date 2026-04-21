<?php
namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Exception;


class TelegramAuthService
{
    private const AUTH_URL = 'https://oauth.telegram.org/auth';
    private const TOKEN_URL = 'https://oauth.telegram.org/token';

    /**
     * Foydalanuvchini Telegram avtorizatsiya sahifasiga yo'naltirish.
     */
    public function redirect()
    {
        $state = Str::random(40);
        $codeVerifier = Str::random(128);

        // PKCE Code Challenge (S256)
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        Session::put('telegram_auth_state', $state);
        Session::put('telegram_code_verifier', $codeVerifier);

        $query = http_build_query([
            'client_id' => config('services.telegram.client_id'),
            'redirect_uri' => url(config('services.telegram.redirect_uri')),
            'response_type' => 'code',
            'scope' => 'openid profile phone telegram:bot_access',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away(self::AUTH_URL . '?' . $query);
    }

    /**
     * Callback'ni qabul qilish va tokenni almashtirish.
     */
    public function handleCallback(array $requestData): array
    {
        // CSRF hujumlarining oldini olish uchun State'ni tekshirish
        $savedState = Session::pull('telegram_auth_state');
        if (empty($savedState) || $savedState !== $requestData['state']) {
            throw new Exception('Invalid state parameter.');
        }

        $clientId = config('services.telegram.client_id');
        $clientSecret = config('services.telegram.client_secret');
        $credentials = base64_encode("{$clientId}:{$clientSecret}");

        // Avtorizatsiya kodini (code) Access va ID tokenlarga almashtirish
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $credentials,
        ])->asForm()->post(self::TOKEN_URL, [
                    'grant_type' => 'authorization_code',
                    'code' => $requestData['code'],
                    'redirect_uri' => url(config('services.telegram.redirect_uri')),
                    'client_id' => $clientId,
                    'code_verifier' => Session::pull('telegram_code_verifier'),
                ]);

        if ($response->failed()) {
            throw new Exception('Failed to fetch Telegram token: ' . $response->body());
        }

        $tokens = $response->json();
        info('tokens', [
            'tokens' => $tokens
        ]);

        /*
         * DIQQAT: Ishlab chiqarish (Production) muhitida id_token imzosini 
         * JWKS orqali (firebase/php-jwt paketi yordamida) validatsiya qilish shart.
         */
        $payload = $this->decodeIdToken($tokens['id_token']);

        return [
            'tokens' => $tokens,
            'user' => $payload,
        ];
    }

    /**
     * JWT tokenni dekod qilish (Signature verifikatsiyasiz oddiy usul).
     */
    private function decodeIdToken(string $idToken): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new Exception('Invalid ID token format.');
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'));
        return json_decode($payload, true);
    }
}