<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">

    <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex-shrink-0 flex items-center font-bold text-xl tracking-wider text-blue-500">
                    MySystem
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm font-medium">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="text-sm text-red-500 hover:text-red-700 dark:hover:text-red-400 font-semibold transition-colors">
                            Tizimdan chiqish
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        <div
            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="p-6 md:p-8 flex flex-col md:flex-row items-center gap-6">

                <div class="flex-shrink-0">
                    @if(auth()->user()->avatar)
                        <img class="h-32 w-32 rounded-full object-cover border-4 border-gray-200 dark:border-gray-700 shadow-md"
                            src="{{ auth()->user()->avatar }}" alt="{{ auth()->user()->name }}">
                    @else
                        <div
                            class="h-32 w-32 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center border-4 border-gray-200 dark:border-gray-700 shadow-md">
                            <span class="text-4xl text-gray-500 dark:text-gray-300">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                        </div>
                    @endif
                </div>

                <div class="flex-1 w-full text-center md:text-left">
                    <h2 class="text-2xl font-bold mb-2">{{ auth()->user()->name }}</h2>
                    <div class="space-y-3 mt-4">
                        <div class="flex flex-col md:flex-row md:items-center text-sm">
                            <span class="text-gray-500 dark:text-gray-400 font-semibold w-32">Telegram ID:</span>
                            <span
                                class="font-mono bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-blue-600 dark:text-blue-400">
                                {{ auth()->user()->telegram_id ?? 'Mavjud emas' }}
                            </span>
                        </div>

                        <div class="flex flex-col md:flex-row md:items-center text-sm">
                            <span class="text-gray-500 dark:text-gray-400 font-semibold w-32">Telefon:</span>
                            <span class="text-gray-800 dark:text-gray-200">
                                {{ auth()->user()->phone ?? 'Foydalanuvchi ruxsat bermagan' }}
                            </span>
                        </div>

                        <div class="flex flex-col md:flex-row md:items-center text-sm">
                            <span class="text-gray-500 dark:text-gray-400 font-semibold w-32">Ro'yxatdan o'tdi:</span>
                            <span class="text-gray-800 dark:text-gray-200">
                                {{ auth()->user()->created_at->format('d.m.Y H:i') }}
                            </span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

</body>

</html>