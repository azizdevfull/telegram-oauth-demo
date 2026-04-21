<?php

use App\Http\Controllers\Auth\TelegramAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
// Dashboard sahifasi (faqat avtorizatsiyadan o'tganlar uchun)
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

Route::post('/logout', function (Request $request) {
    Auth::logout();

    // Sessiyani tozalash va CSRF tokenni yangilash (Xavfsizlik uchun muhim)
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/')->with('status', 'Tizimdan muvaffaqiyatli chiqdingiz.');
})->name('logout');

Route::get('/auth/telegram/redirect', [TelegramAuthController::class, 'redirect'])->name('telegram.login');
Route::get('/auth/telegram/callback', [TelegramAuthController::class, 'callback'])->name('telegram.callback');