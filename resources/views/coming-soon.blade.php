<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Coming Soon — {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link rel="stylesheet" href="{{ asset('css/fallback.css') }}">
    @endif
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="min-h-screen flex flex-col items-center justify-center text-center px-6">
    <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mb-5">
        <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    </div>

    <h1 class="text-2xl font-bold text-gray-800 mb-2">Dashboard Coming Soon</h1>
    <p class="text-gray-500 text-sm max-w-xs mb-6">
        This section is currently being built. Check back shortly.
    </p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit"
                class="text-sm text-indigo-600 hover:text-indigo-800 font-medium transition">
            Sign out
        </button>
    </form>
</div>

</body>
</html>
