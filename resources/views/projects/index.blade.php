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
<body class="project-picker-shell min-h-screen font-sans antialiased text-slate-900">
    <div class="min-h-screen">
        <header class="project-picker-header">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-sm font-bold text-white shadow-lg shadow-indigo-950/20">
                        PT
                    </div>
                    <div>
                        <p class="text-sm font-bold text-slate-900">Project Tracker</p>
                        <p class="text-xs font-medium text-slate-500">Project workspace</p>
                    </div>
                </div>

                @auth
                    <div class="flex items-center gap-4">
                        <div class="hidden text-right sm:block">
                            <p class="text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500">{{ auth()->user()->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                Sign out
                            </button>
                        </form>
                    </div>
                @endauth
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-8 lg:py-10" style="max-width: 90rem; padding-top: 2rem; padding-bottom: 2.5rem;">
            @if(session('error'))
                <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="project-picker-heading">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">My Projects</h1>
                    <p class="mt-2 text-sm text-slate-600">
                        Choose a project to open the dashboard for your role in that project.
                    </p>
                </div>
                <span class="project-count-pill">
                    {{ $projects->count() }} project{{ $projects->count() === 1 ? '' : 's' }}
                </span>
            </div>

            @if($projects->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-slate-900">No assigned projects yet</h3>
                    <p class="mt-2 text-sm text-slate-500">
                        Projects will appear here once you are added as a team lead or member.
                    </p>
                </div>
            @else
                <div class="project-picker-grid">
                    @foreach($projects as $item)
                        @php
                            $project = $item['project'];
                            $statusClass = match($project->status) {
                                'active' => 'bg-emerald-100 text-emerald-700',
                                'on_hold' => 'bg-amber-100 text-amber-700',
                                'completed' => 'bg-blue-100 text-blue-700',
                                default => 'bg-slate-100 text-slate-600',
                            };
                            $teamOverflow = max(0, $item['teams']->count() - 4);
                        @endphp

                        <article class="project-picker-card group" style="min-height: 390px;">
                            <div class="project-picker-card-body" style="padding: 1.05rem;">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="truncate text-base font-bold text-slate-950 group-hover:text-indigo-700">
                                            {{ $project->name }}
                                        </h3>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ $item['teams']->count() }} team{{ $item['teams']->count() !== 1 ? 's' : '' }}
                                        </p>
                                    </div>
                                    <span class="project-status-pill {{ $statusClass }}">
                                        {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                                    </span>
                                </div>

                                <p class="mt-4 line-clamp-2 min-h-[2.5rem] text-sm leading-5 text-slate-600" style="margin-top: 0.875rem; min-height: 2.25rem;">
                                    {{ $project->description ?: 'No description provided.' }}
                                </p>

                                <div class="project-team-list-frame" style="position: relative; margin-top: 0.875rem;">
                                    <div class="project-team-list" style="max-height: calc((3.55rem * 4) + (0.5rem * 3)); overflow-y: auto; scroll-behavior: smooth;">
                                    @foreach($item['teams'] as $team)
                                        @php
                                            $teamRole = $team->pivot->role ?? 'member';
                                            $roleLabel = $teamRole === 'lead' ? 'Team Lead' : 'Member';
                                            $roleClass = $teamRole === 'lead'
                                                ? 'bg-indigo-50 text-indigo-700 border-indigo-100'
                                                : 'bg-emerald-50 text-emerald-700 border-emerald-100';
                                        @endphp
                                        <a href="{{ route('projects.open', ['project' => $project, 'team' => $team->id]) }}"
                                           class="project-team-link focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" style="min-height: 3.55rem; padding: 0.6rem 0.75rem; border-radius: 0.7rem;">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-bold text-slate-800">
                                                    {{ $team->name }}
                                                </p>
                                                <span class="project-role-pill {{ $roleClass }}">
                                                    Opens as {{ $roleLabel }}
                                                </span>
                                            </div>
                                            <span class="shrink-0 text-sm font-bold text-indigo-600">
                                                Open
                                            </span>
                                        </a>
                                    @endforeach
                                    </div>
                                </div>

                                <div class="mt-auto border-t border-slate-100 pt-4 text-sm" style="padding-top: 0.875rem;">
                                    <span class="text-slate-500">
                                        @if($project->start_date || $project->end_date)
                                            {{ $project->start_date?->format('M d, Y') ?? 'TBD' }}
                                            @if($project->end_date)
                                                - {{ $project->end_date->format('M d, Y') }}
                                            @endif
                                        @else
                                            Dates not set
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </main>
    </div>
</body>
</html>
