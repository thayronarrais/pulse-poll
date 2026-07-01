<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('admin.page_title_surveys') }}</title>
    {{-- Alpine comes from the Vite bundle; no CDN to avoid double Alpine. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased">
    <div class="min-h-screen flex flex-col">
        <!-- Navbar -->
        <nav class="bg-indigo-600 shadow-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2 sm:py-0 sm:h-16">
                    <div class="flex items-center gap-4 sm:gap-8">
                        <a href="{{ route('admin.surveys.index') }}" class="text-white font-bold text-xl tracking-tight">
                            {{ __('admin.brand') }}
                        </a>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('admin.surveys.index') }}"
                               class="px-3 py-2 rounded-md text-sm font-medium transition whitespace-nowrap {{ request()->routeIs('admin.surveys.*') ? 'bg-indigo-700 text-white' : 'text-indigo-100 hover:bg-indigo-500 hover:text-white' }}">
                                {{ __('admin.nav.surveys') }}
                            </a>
                            <a href="{{ route('admin.live-sessions.index') }}"
                               class="px-3 py-2 rounded-md text-sm font-medium transition whitespace-nowrap {{ request()->routeIs('admin.live-sessions.*') ? 'bg-indigo-700 text-white' : 'text-indigo-100 hover:bg-indigo-500 hover:text-white' }}">
                                {{ __('admin.nav.live_sessions') }}
                            </a>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-4">
                        <x-locale-switcher :dark="true" />
                        @auth
                            <span class="hidden sm:inline text-sm text-indigo-100">{{ auth()->user()->name }}</span>
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

        <!-- Main Content -->
        <main class="flex-grow max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 w-full">
            @if(session('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm" role="alert">
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm">
                    <ul class="list-disc ml-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
