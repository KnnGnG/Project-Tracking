<x-guest-layout>
<div class="min-h-screen flex">

    {{-- ── Left brand panel ───────────────────────────────────────────────── --}}
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-indigo-900 via-indigo-800 to-indigo-700 flex-col justify-between p-12">
        {{-- Logo --}}
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white/20 backdrop-blur rounded-xl flex items-center justify-center font-bold text-white text-base">
                PT
            </div>
            <span class="text-white text-xl font-semibold tracking-tight">Project Tracker</span>
        </div>

        {{-- Headline --}}
        <div>
            <h1 class="text-4xl font-bold text-white leading-tight mb-4">
                Track. Collaborate.<br>Deliver.
            </h1>
            <p class="text-indigo-200 text-lg leading-relaxed mb-10">
                Keep every project on schedule — from the first task to the final milestone.
            </p>

            {{-- Feature highlights --}}
            <div class="space-y-5">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-sm">Real-time progress tracking</p>
                        <p class="text-indigo-300 text-sm">Visual dashboards for every team and client.</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-sm">Calendar-based milestones</p>
                        <p class="text-indigo-300 text-sm">Clients see exactly where the project stands.</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 20h5v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2h5M12 12a4 4 0 100-8 4 4 0 000 8z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-sm">Role-based access</p>
                        <p class="text-indigo-300 text-sm">Admins, leads, members and clients — each sees what they need.</p>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-indigo-400 text-xs">&copy; {{ date('Y') }} Project Tracker. All rights reserved.</p>
    </div>

    {{-- ── Right form panel ────────────────────────────────────────────────── --}}
    <div class="flex-1 flex flex-col justify-center items-center px-6 sm:px-12 lg:px-16 py-12">
        {{-- Mobile logo --}}
        <div class="flex items-center gap-2 mb-10 lg:hidden">
            <div class="w-9 h-9 bg-indigo-600 rounded-xl flex items-center justify-center font-bold text-white text-sm">PT</div>
            <span class="text-gray-900 text-lg font-semibold">Project Tracker</span>
        </div>

        <div class="w-full max-w-md">
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-gray-900">Welcome back</h2>
                <p class="text-gray-500 mt-1 text-sm">Sign in to your account to continue.</p>
            </div>

            {{-- Validation errors --}}
            @if ($errors->any())
                <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl">
                    <ul class="text-sm text-red-600 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Status message (e.g. password reset) --}}
            @session('status')
                <x-floating-notification :message="$value" />
            @endsession

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Email address
                    </label>
                    <input id="email"
                           type="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           autocomplete="username"
                           class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition @error('email') border-red-400 @enderror"
                           placeholder="you@example.com">
                </div>

                {{-- Password --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password
                        </label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}"
                               class="text-xs text-indigo-600 hover:text-indigo-800 font-medium transition">
                                Forgot password?
                            </a>
                        @endif
                    </div>
                    <input id="password"
                           type="password"
                           name="password"
                           required
                           autocomplete="current-password"
                           class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition @error('password') border-red-400 @enderror"
                           placeholder="••••••••">
                </div>

                {{-- Remember me --}}
                <div class="flex items-center gap-2">
                    <input id="remember_me"
                           type="checkbox"
                           name="remember"
                           class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <label for="remember_me" class="text-sm text-gray-600">Remember me</label>
                </div>

                {{-- Submit --}}
                <button type="submit"
                        class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Sign in
                </button>
            </form>

            {{-- Register link --}}
            @if (Route::has('register'))
                <p class="mt-6 text-center text-sm text-gray-500">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="text-indigo-600 hover:text-indigo-800 font-medium transition">
                        Create one
                    </a>
                </p>
            @endif
        </div>
    </div>

</div>
</x-guest-layout>
