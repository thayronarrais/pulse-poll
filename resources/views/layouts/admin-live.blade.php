<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('admin.page_title_live') }}</title>
    {{-- Alpine + Echo come from the Vite bundle; no CDN to avoid double Alpine. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased">
    <div class="min-h-screen flex flex-col">
        <nav class="bg-indigo-600 shadow-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <div class="flex items-center gap-8">
                        <a href="{{ route('admin.surveys.index') }}" class="text-white font-bold text-xl tracking-tight">
                            {{ __('admin.brand') }}
                        </a>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('admin.surveys.index') }}"
                               class="px-3 py-2 rounded-md text-sm font-medium transition {{ request()->routeIs('admin.surveys.*') ? 'bg-indigo-700 text-white' : 'text-indigo-100 hover:bg-indigo-500 hover:text-white' }}">
                                {{ __('admin.nav.surveys') }}
                            </a>
                            <a href="{{ route('admin.live-sessions.index') }}"
                               class="px-3 py-2 rounded-md text-sm font-medium transition {{ request()->routeIs('admin.live-sessions.*') ? 'bg-indigo-700 text-white' : 'text-indigo-100 hover:bg-indigo-500 hover:text-white' }}">
                                {{ __('admin.nav.live_sessions') }}
                            </a>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-locale-switcher :dark="true" />
                        @auth
                            <span class="text-sm text-indigo-100">{{ auth()->user()->name }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-2 rounded-md text-sm font-medium text-indigo-100 hover:bg-indigo-500 hover:text-white transition">
                                    {{ __('admin.nav.logout') }}
                                </button>
                            </form>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <main class="flex-grow max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 w-full">
            @if(session('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm" role="alert">
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
