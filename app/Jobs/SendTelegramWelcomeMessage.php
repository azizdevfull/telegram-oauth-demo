<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTelegramWelcomeMessage implements ShouldQueue
{
    use Queueable;

    /**
     * Job takrorlanishlar soni (fail bo'lsa, necha marta qayta urinish kerak)
     */
    public int $tries = 3;

    public function __construct(
        private readonly string $telegramId,
        private readonly string $message
    ) {}

    public function handle(): void
    {
        $botToken = config('services.telegram.bot_token');
        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $response = Http::post($apiUrl, [
            'chat_id' => $this->telegramId,
            'text' => $this->message,
            'parse_mode' => 'HTML',
        ]);

        if ($response->failed()) {
            // Agar foydalanuvchi botni bloklagan bo'lsa yoki xatolik yuz bersa, logga yozamiz
            Log::error('Telegram xabar yuborishda xatolik yuz berdi.', [
                'telegram_id' => $this->telegramId,
                'response' => $response->json(),
            ]);

            $response->throw();
        }
    }
}
