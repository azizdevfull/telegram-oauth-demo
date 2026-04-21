<a href="{{ route('telegram.login') }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center px-4 py-2 bg-[#24A1DE] border border-transparent rounded-md font-semibold text-white hover:bg-[#1d82b3] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#24A1DE] transition ease-in-out duration-150 shadow-sm']) }}>

    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path
            d="M12 24c6.627 0 12-5.373 12-12S18.627 0 12 0 0 5.373 0 12s5.373 12 12 12zm5.894-15.779l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.295-.6.295-.002 0-.003 0-.005 0l.213-3.054 5.56-5.022c.24-.213-.054-.334-.373-.121l-6.869 4.326-2.96-.924c-.64-.203-.658-.64.135-.954l11.566-4.458c.538-.196 1.006.128.832.941z" />
    </svg>

    {{ $slot->isEmpty() ? 'Telegram orqali kirish' : $slot }}
</a>