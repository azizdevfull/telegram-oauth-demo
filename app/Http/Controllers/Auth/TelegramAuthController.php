<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendTelegramWelcomeMessage;
use App\Services\TelegramAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Exception;
use Illuminate\Support\Str;

class TelegramAuthController extends Controller
{
    public function __construct(
        private readonly TelegramAuthService $telegramAuthService
    ) {
    }

    public function redirect()
    {
        return $this->telegramAuthService->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $data = $this->telegramAuthService->handleCallback($request->all());
            info('data', [
                'data' => $data
            ]);
            $telegramUser = $data['user'];
            // Tizimda foydalanuvchini topish yoki yangi yaratish
            $user = User::firstOrCreate(
                ['telegram_id' => $telegramUser['id']],
                [
                    'name' => $telegramUser['name'] ?? 'Telegram User',
                    'phone' => $telegramUser['phone_number'] ?? null,
                    'avatar' => $telegramUser['picture'] ?? null,
                    'password' => bcrypt(Str::random(16)), // Yoki auth uchun mos boshqa yo'l
                ]
            );

            Auth::login($user);
            // Avtorizatsiya yakunlangach asinxron ravishda xabarni navbatga qo'shish
            $welcomeText = "Assalomu alaykum, <b>{$user->name}</b>! Tizimimizga xush kelibsiz. Endi xabarnomalarni to'g'ridan-to'g'ri shu bot orqali qabul qilasiz.";

            SendTelegramWelcomeMessage::dispatch($user->telegram_id, $welcomeText);
            return redirect()->intended('/dashboard');

        } catch (Exception $e) {
            // Log yozish va xatolik sahifasiga yo'naltirish
            report($e);
            return redirect('/login')->with('error', 'Telegram orqali avtorizatsiya amalga oshmadi.');
        }
    }
}