# Laravel — Telegram OAuth 2.0 + PKCE Qo'llanmasi

> **Telegram Login** — foydalanuvchilarni Telegram hisobi orqali xavfsiz tarzda tizimga kiritish.  
> Ushbu loyiha **OAuth 2.0 + PKCE** standartini Laravel'da qanday amalga oshirishni ko'rsatadi.

---

## Mundarija

- [Loyiha haqida](#loyiha-haqida)
- [Arxitektura](#arxitektura)
- [OAuth 2.0 + PKCE jarayoni](#oauth-20--pkce-jarayoni)
- [O'rnatish](#ornatish)
- [Muhit sozlamalari (.env)](#muhit-sozlamalari-env)
- [Telegram Bot yaratish](#telegram-bot-yaratish)
- [Fayl tuzilishi](#fayl-tuzilishi)
- [Kod tushuntirmasi](#kod-tushuntirmasi)
- [Mavjud Scope'lar (Ruxsatlar)](#mavjud-scopelar-ruxsatlar)
- [Xavfsizlik eslatmalari](#xavfsizlik-eslatmalari)
- [Ishga tushirish](#ishga-tushirish)

---

## Loyiha haqida

Bu loyiha Telegram'ning rasmiy **OAuth 2.0** tizimi (`oauth.telegram.org`) orqali foydalanuvchilarni autentifikatsiya qilishni amalga oshiradi. An'anaviy email/parol o'rniga foydalanuvchi faqat "**Telegram orqali kirish**" tugmasini bosadi va Telegram hisobi ma'lumotlari (ism, telefon, avatar) avtomatik tarzda tizimga olinadi.

### Asosiy imkoniyatlar

| Imkoniyat | Tavsif |
|---|---|
| **OAuth 2.0 + PKCE** | Authorization Code Flow with Proof Key for Code Exchange |
| **State parametri** | CSRF hujumlaridan himoya |
| **Xush kelibsiz xabar** | Kirish muvaffaqiyatli bo'lganda foydalanuvchiga bot orqali xabar yuboriladi |
| **Queue (Navbat)** | Telegram xabarni asinxron (fon rejimida) yuboradi |
| **Blade komponenti** | `<x-telegram-login-button />` — qayta ishlatiladigan UI komponenti |

---

## Arxitektura

```
┌─────────────────────────────────────────────────────────────┐
│                        FOYDALANUVCHI                         │
└─────────────────────────┬───────────────────────────────────┘
                          │  1. "Telegram orqali kirish" tugmasi
                          ▼
┌─────────────────────────────────────────────────────────────┐
│              TelegramAuthController::redirect()              │
│  • State va PKCE Code Verifier generatsiya qiladi           │
│  • Sessiyaga saqlaydi                                        │
│  • oauth.telegram.org/auth ga yo'naltiradi                  │
└─────────────────────────┬───────────────────────────────────┘
                          │  2. Telegram sahifasida login
                          ▼
┌─────────────────────────────────────────────────────────────┐
│               oauth.telegram.org  (Telegram)                 │
│  • Foydalanuvchi ruxsat beradi                              │
│  • ?code=...&state=... bilan callback ga qaytadi            │
└─────────────────────────┬───────────────────────────────────┘
                          │  3. Callback
                          ▼
┌─────────────────────────────────────────────────────────────┐
│              TelegramAuthController::callback()              │
│  • State ni tekshiradi (CSRF himoya)                        │
│  • Code ni token bilan almashtiradi                         │
│  • ID Token (JWT) ni dekod qiladi                           │
│  • User::firstOrCreate() — foydalanuvchi yaratadi/topadi    │
│  • Auth::login() — tizimga kiritadi                         │
│  • SendTelegramWelcomeMessage job ni navbatga qo'shadi      │
└─────────────────────────┬───────────────────────────────────┘
                          │  4. /dashboard ga yo'naltiradi
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                   Queue Worker (fon)                         │
│  • SendTelegramWelcomeMessage::handle()                     │
│  • Telegram Bot API orqali xush kelibsiz xabar yuboradi     │
└─────────────────────────────────────────────────────────────┘
```

---

## OAuth 2.0 + PKCE jarayoni

**PKCE** (Proof Key for Code Exchange) — Authorization Code Flow ni yanada xavfsiz qiluvchi mexanizm. Asosiy maqsad: agar `code` ushlab qolinsa ham, tokenga aylantirib bo'lmasin.

```
1. redirect() da:
   code_verifier  = random(128 belgi)
   code_challenge = base64url( sha256(code_verifier) )
   
   → Telegram ga: ?code_challenge=...&code_challenge_method=S256

2. callback() da:
   → Telegram ga: code + code_verifier yuboramiz
   → Telegram tekshiradi: sha256(code_verifier) == code_challenge?
   → Mos kelsa, access_token + id_token qaytaradi
```

---

## O'rnatish

### Talablar

- PHP >= 8.2
- Composer
- MySQL yoki PostgreSQL
- Telegram Bot (BotFather orqali)

### Qadamlar

```bash
# 1. Reponi klonlash
git clone <repo-url>
cd oauth-telegram

# 2. Paketlarni o'rnatish
composer install
npm install

# 3. Muhit faylini nusxalash
cp .env.example .env

# 4. Kalit generatsiya qilish
php artisan key:generate

# 5. .env faylini to'ldirish (quyida ko'rsatilgan)

# 6. Ma'lumotlar bazasini yaratish
php artisan migrate

# 7. Queue worker ishga tushirish (alohida terminalda)
php artisan queue:work

# 8. Serverni ishga tushirish
php artisan serve
```

---

## Muhit sozlamalari (.env)

`.env.example` faylini `.env` ga nusxalab, quyidagilarni to'ldiring:

```env
APP_URL=http://localhost:8000

# ── Telegram OAuth ──────────────────────────────────────
TELEGRAM_CLIENT_ID=        # BotFather bergan client_id
TELEGRAM_CLIENT_SECRET=    # BotFather bergan client_secret
TELEGRAM_REDIRECT_URI=/auth/telegram/callback
TELEGRAM_BOT_TOKEN=        # Bot tokeni (xabar yuborish uchun)

# ── Ma'lumotlar bazasi ───────────────────────────────────
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oauth_telegram
DB_USERNAME=root
DB_PASSWORD=

# ── Sessiya va Navbat ────────────────────────────────────
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

---

## Telegram Bot yaratish

Telegram OAuth ishlashi uchun **OAuth-ga ulangan bot** kerak. Bu oddiy bot emas — `oauth.telegram.org` tomonidan tasdiqlangan bot bo'lishi shart.

### BotFather orqali sozlash

```
1. Telegram da @BotFather ga yozing
2. /newbot → bot yarating, token oling (TELEGRAM_BOT_TOKEN)
3. /mybots → botingizni tanlang
4. "Bot Settings" → "OAuth Settings" (yoki /setdomain)
5. Domeningizni kiritish: masalan, localhost yoki ngrok URL
6. BotFather sizga client_id va client_secret beradi
```

> **Muhim:** `APP_URL` dagi domen BotFather'da ro'yxatdan o'tgan domen bilan **bir xil** bo'lishi shart. Lokal ishlab chiqishda [ngrok](https://ngrok.com) yoki `localtunnel` ishlating.

---

## Fayl tuzilishi

```
app/
├── Http/
│   └── Controllers/
│       └── Auth/
│           └── TelegramAuthController.php   # Redirect va Callback
├── Jobs/
│   └── SendTelegramWelcomeMessage.php       # Xabar yuborish (Queue)
├── Models/
│   └── User.php                             # telegram_id, phone, avatar maydonlari
└── Services/
    └── TelegramAuthService.php              # PKCE logikasi, token almashinuvi

resources/views/
├── components/
│   └── telegram-login-button.blade.php     # Qayta ishlatiladigan tugma komponenti
├── welcome.blade.php                        # Kirish sahifasi
└── dashboard.blade.php                      # Shaxsiy kabinet

database/migrations/
└── 0001_01_01_000000_create_users_table.php # telegram_id, phone, avatar ustunlari

routes/
└── web.php                                  # /auth/telegram/redirect va /callback
```

---

## Kod tushuntirmasi

### `TelegramAuthService` — asosiy mantiq

**`redirect()` metodi:**

```php
// 1. Tasodifiy state yaratamiz (CSRF himoya uchun)
$state = Str::random(40);

// 2. PKCE: code_verifier (128 belgili) va undan code_challenge hosil qilamiz
$codeVerifier  = Str::random(128);
$codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

// 3. Sessiyaga saqlaymiz (callback da tekshirish uchun)
Session::put('telegram_auth_state',   $state);
Session::put('telegram_code_verifier', $codeVerifier);

// 4. Foydalanuvchini Telegram sahifasiga yo'naltiramiz
redirect()->away('https://oauth.telegram.org/auth?' . http_build_query([...]));
```

**`handleCallback()` metodi:**

```php
// 1. State ni tekshiramiz — boshqa saytdan kelgan so'rov emas ekanini tasdiqlaymiz
if ($savedState !== $requestData['state']) {
    throw new Exception('Invalid state parameter.');
}

// 2. Authorization code ni token bilan almashtiramiz
// Basic Auth: base64(client_id:client_secret) sarlavhasi bilan
Http::withHeaders(['Authorization' => 'Basic ' . $credentials])
    ->asForm()->post(TOKEN_URL, [
        'grant_type'    => 'authorization_code',
        'code'          => $requestData['code'],
        'code_verifier' => Session::pull('telegram_code_verifier'),
    ]);

// 3. JWT (id_token) ni dekod qilib foydalanuvchi ma'lumotlarini olamiz
$payload = $this->decodeIdToken($tokens['id_token']);
// payload: id, name, phone_number, picture ...
```

### `TelegramAuthController` — foydalanuvchi yaratish

```php
// Telegram ID bo'yicha foydalanuvchi topamiz yoki yangi yaratamiz
$user = User::firstOrCreate(
    ['telegram_id' => $telegramUser['id']],
    [
        'name'   => $telegramUser['name'],
        'phone'  => $telegramUser['phone_number'],
        'avatar' => $telegramUser['picture'],
    ]
);

Auth::login($user);

// Fon rejimida xabar yuboramiz (asosiy so'rov kechiktirmaydi)
SendTelegramWelcomeMessage::dispatch($user->telegram_id, $welcomeText);
```

### `<x-telegram-login-button />` — Blade komponenti

```blade
{{-- Standart ko'rinish --}}
<x-telegram-login-button />

{{-- O'z matni bilan --}}
<x-telegram-login-button>
    Telegram orqali ro'yxatdan o'ting
</x-telegram-login-button>

{{-- CSS klassi qo'shib --}}
<x-telegram-login-button class="w-64" />
```

---

## Mavjud Scope'lar (Ruxsatlar)

Avtorizatsiya so'rovida qaysi ma'lumotlarni so'rashni `scope` parametri orqali belgilaysiz. `openid` scope **majburiy**.

| Scope | Tavsif | Qaytariladigan claim'lar |
|---|---|---|
| `openid` | **Majburiy.** Foydalanuvchining unikal identifikatori va autentifikatsiya vaqti. | `sub`, `iss`, `iat`, `exp` |
| `profile` | Foydalanuvchining asosiy ma'lumotlari: ism, username, profil rasmi URL. | `name`, `preferred_username`, `picture` |
| `phone` | Foydalanuvchining tasdiqlangan telefon raqami. Foydalanuvchi alohida ruxsat berishi kerak. | `phone_number` |
| `telegram:bot_access` | Kirish amalga oshirilgandan so'ng botingizga foydalanuvchiga to'g'ridan-to'g'ri xabar yuborish imkonini beradi. | — |

### Loyihadagi sozlama

`TelegramAuthService::redirect()` metodida scope'lar quyidagicha so'ralgan:

```php
'scope' => 'openid profile phone telegram:bot_access',
```

> **Eslatma:** `phone` scope'ini so'rasangiz, foydalanuvchidan **alohida ruxsat** so'raladi. Ba'zi foydalanuvchilar rad etishi mumkin — shuning uchun `phone_number` claim'i `null` bo'lishi ehtimolini kodda ko'rib chiqing (User modelda `nullable()` sifatida saqlangan).
>
> `telegram:bot_access` scope'i bo'lmasa, `SendTelegramWelcomeMessage` job xabar yubora olmaydi — shuning uchun bu scope ham so'rovda bo'lishi shart.

---

## Xavfsizlik eslatmalari

| Muammo | Yechim |
|---|---|
| **CSRF hujumi** | `state` parametri sessiyada saqlanib, callback da tekshiriladi |
| **Code interception** | PKCE: `code_verifier` sessiyada, `code_challenge` Telegram'da — ikkalasi bo'lmasa token olmaydi |
| **JWT imzosi** | ⚠️ Hozir imzo **tekshirilmayapti** — production'da `firebase/php-jwt` bilan JWKS orqali tekshiring |
| **Bot token** | `.env` da saqlash, hech qachon kodga yozmaslik |
| **HTTPS** | Production'da albatta SSL sertifikati bo'lishi shart |

> **Production uchun muhim qo'shimcha:**  
> `TelegramAuthService::decodeIdToken()` metodini `firebase/php-jwt` paketi bilan almashtirib, Telegram'ning JWKS endpointidan kalitlarni olib, JWT imzosini tekshiring.

---

## Ishga tushirish

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Queue worker (xabarlar uchun)
php artisan queue:work

# Brauzerda oching
open http://localhost:8000
```

Kirish sahifasida **"Telegram orqali kirish"** tugmasini bosing → Telegram sahifasiga o'tasiz → Ruxsat bering → Dashboard sahifasiga qaytasiz.

---

## Litsenziya

MIT License — erkin foydalaning, o'rganing va tarqating.
