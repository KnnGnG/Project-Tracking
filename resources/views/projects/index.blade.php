<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>My Projects</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link rel="stylesheet" href="{{ asset('css/fallback.css') }}">
    @endif
</head>
<body class="min-h-screen bg-gray-100 font-sans antialiased text-gray-900">
    <div class="min-h-screen">
        <header class="border-b border-gray-200 bg-white/95 shadow-sm">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-sm font-bold text-white">
                        PT
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Project Tracker</p>
                        <p class="text-xs text-gray-500">Project workspace</p>
                    </div>
                </div>

                @auth
                    <div class="flex items-center gap-4">
                        <div class="hidden text-right sm:block">
                            <p class="text-sm font-semibold text-gray-800">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-500">{{ auth()->user()->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 hover:text-gray-900">
                                Sign out
                            </button>
                        </form>
                    </div>
                @endauth
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-8 lg:py-10">
            @if(session('error'))
                <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="ui-page-heading">
                <div>
                    <h1>My Projects</h1>
                    <p class="mt-2 text-sm text-gray-500">
                        Choose a project to open the dashboard for your role in that project.
                    </p>
                </div>
                <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-500 shadow-sm ring-1 ring-gray-200">
                    {{ $projects->count() }} project{{ $projects->count() === 1 ? '' : 's' }}
                </span>
            </div>

            @if($projects->isEmpty())
                <div class="ui-soft-panel px-6 py-16 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-gray-900">No assigned projects yet</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        Projects will appear here once you are added as a team lead or member.
                    </p>
                </div>
            @else
                <div class="project-card-grid">
                    @foreach($projects as $item)
                        @php
                            $project = $item['project'];
                            $statusClass = match($project->status) {
                                'active' => 'bg-green-100 text-green-700',
                                'on_hold' => 'bg-yellow-100 text-yellow-700',
                                'completed' => 'bg-blue-100 text-blue-700',
                                default => 'bg-gray-100 text-gray-600',
                            };
                        @endphp

                        <article class="project-card group flex min-h-[260px] flex-col border bg-white p-5 transition hover:-translate-y-0.5 hover:border-indigo-200">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-base font-semibold text-gray-900 group-hover:text-indigo-700">
                                        {{ $project->name }}
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ $item['teams']->count() }} team{{ $item['teams']->count() !== 1 ? 's' : '' }}
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                    {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                                </span>
                            </div>

                            <p class="mt-4 line-clamp-2 min-h-[2.5rem] text-sm leading-5 text-gray-600">
                                {{ $project->description ?: 'No description provided.' }}
                            </p>

                            <div class="mt-5 max-h-40 space-y-2 overflow-y-auto pr-1">
                                @foreach($item['teams']->take(4) as $team)
                                    @php
                                        $teamRole = $team->pivot->role ?? 'member';
                                        $roleLabel = $teamRole === 'lead' ? 'Team Lead' : 'Member';
                                        $roleClass = $teamRole === 'lead'
                                            ? 'bg-indigo-50 text-indigo-700 border-indigo-100'
                                            : 'bg-emerald-50 text-emerald-700 border-emerald-100';
                                    @endphp
                                    <a href="{{ route('projects.open', ['project' => $project, 'team' => $team->id]) }}"
                                       class="group flex items-center justify-between gap-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 transition hover:border-indigo-300 hover:bg-white hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-gray-800 group-hover:text-indigo-700">
                                                {{ $team->name }}
                                            </p>
                                            <span class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $roleClass }}">
                                                Opens as {{ $roleLabel }}
                                            </span>
                                        </div>
                                        <span class="shrink-0 text-sm font-semibold text-indigo-600 group-hover:text-indigo-700">
                                            Open
                                        </span>
                                    </a>
                                @endforeach
                                @if($item['teams']->count() > 4)
                                    <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500">
                                        +{{ $item['teams']->count() - 4 }} more
                                    </span>
                                @endif
                            </div>

                            <div class="mt-auto flex items-center justify-between gap-3 border-t border-gray-100 pt-4 text-sm">
                                <span class="text-gray-500">
                                    @if($project->start_date || $project->end_date)
                                        {{ $project->start_date?->format('M d, Y') ?? 'TBD' }}
                                        @if($project->end_date)
                                            - {{ $project->end_date->format('M d, Y') }}
                                        @endif
                                    @else
                                        Dates not set
                                    @endif
                                </span>
                                <a href="{{ route('projects.open', $project) }}"
                                   class="shrink-0 rounded-lg px-2 py-1 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    Open project
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </main>
    </div>
</body>
</html>

