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
    @php
        $projectNotificationsPath = public_path('css/project-notifications.css');
        $projectNotificationsVersion = file_exists($projectNotificationsPath) ? filemtime($projectNotificationsPath) : '1';
    @endphp
    <link rel="stylesheet" href="{{ asset('css/project-notifications.css') }}?v={{ $projectNotificationsVersion }}">
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

        <main class="mx-auto max-w-[90rem] px-6 py-8 lg:py-10">
            @if(session('error'))
                <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                    {{ session('error') }}
                </div>
            @endif
            @if(session('success'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @php
                $statusLabels = [
                    'active' => 'Active',
                    'overdue' => 'Overdue',
                    'near_due' => 'Near Due',
                    'upcoming' => 'Upcoming',
                    'on_hold' => 'On Hold',
                    'completed' => 'Completed',
                    'not_set' => 'Not Set',
                ];
                $sortLabels = [
                    'name' => 'Name (A–Z)',
                    'status' => 'Status',
                    'start_date' => 'Start Date',
                    'end_date' => 'End Date',
                ];
            @endphp
            <div class="project-picker-heading">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">My Projects</h1>
                    <p class="mt-2 text-sm text-slate-600">
                        Choose a project to open the dashboard for your role in that project.
                    </p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <span class="project-count-pill">
                        {{ $projects->count() }} project{{ $projects->count() === 1 ? '' : 's' }}
                    </span>
                    <form method="GET" action="{{ route('projects.index') }}" class="flex flex-wrap items-end gap-3">
                        <label class="flex flex-col gap-1 text-xs font-semibold text-slate-500">
                            <span>Status</span>
                            <select name="status" onchange="this.form.submit()"
                                    class="w-48 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-10 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="all" @selected($statusFilter === 'all')>All statuses</option>
                                @foreach($statusOptions as $statusOption)
                                    <option value="{{ $statusOption }}" @selected($statusFilter === $statusOption)>
                                        {{ $statusLabels[$statusOption] ?? ucfirst(str_replace('_', ' ', $statusOption)) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="flex flex-col gap-1 text-xs font-semibold text-slate-500">
                            <span>Sort by</span>
                            <select name="sort" onchange="this.form.submit()"
                                    class="w-48 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-10 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                @foreach($sortLabels as $sortValue => $sortLabel)
                                    <option value="{{ $sortValue }}" @selected($sort === $sortValue)>{{ $sortLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                        @if($statusFilter !== 'all' || $sort !== 'name')
                            <a href="{{ route('projects.index') }}"
                               class="rounded-lg px-3 py-2 text-sm font-semibold text-indigo-600 hover:underline">
                                Reset
                            </a>
                        @endif
                    </form>
                </div>
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
                            $displayStatus = $project->effectiveStatus();
                            $statusLabel = $project->effectiveStatusLabel();
                            $statusClass = match($displayStatus) {
                                'active' => 'bg-emerald-100 text-emerald-700',
                                'overdue' => 'bg-red-100 text-red-700',
                                'near_due' => 'bg-amber-100 text-amber-700',
                                'upcoming' => 'bg-sky-100 text-sky-700',
                                'on_hold' => 'bg-slate-200 text-slate-700',
                                'completed' => 'bg-indigo-100 text-indigo-700',
                                default => 'bg-slate-100 text-slate-600',
                            };
                            $projectTaskItems = $projectTaskOverview
                                ->filter(fn (array $taskItem) => (int) $taskItem['task']->project_id === (int) $project->id);
                            $projectNewTaskCount = $projectTaskItems->whereNotNull('notification')->count();
                            $projectNewTaskItem = $projectTaskItems->first(fn (array $taskItem) => ! is_null($taskItem['notification']));
                        @endphp

                        <article class="project-picker-card project-card-state-{{ str_replace('_', '-', $displayStatus) }} group" data-project-id="{{ $project->id }}">
                            <div class="project-picker-card-body">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="truncate text-base font-bold text-slate-950 group-hover:text-indigo-700">
                                            {{ $project->name }}
                                        </h3>
                                        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                                            <span>{{ $item['teams']->count() }} team{{ $item['teams']->count() !== 1 ? 's' : '' }}</span>
                                        </p>
                                    </div>
                                    <div class="project-card-heading-badges">
                                        @if($projectNewTaskCount > 0)
                                            @php
                                                $newTaskItems = $projectTaskItems
                                                    ->whereNotNull('notification')
                                                    ->sortBy(fn (array $taskItem) => [
                                                        $taskItem['status'] === 'overdue' ? 0 : 1,
                                                        -$taskItem['notification']->created_at->timestamp,
                                                    ])
                                                    ->values();
                                            @endphp
                                            <div class="project-task-indicator">
                                                <form method="POST" action="{{ route('projects.new-tasks.open', $projectNewTaskItem['notification']) }}">
                                                    @csrf
                                                    <button type="submit" class="project-task-notification-count" aria-label="Open a new task from {{ $project->name }}. {{ $projectNewTaskCount }} unread.">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/>
                                                        </svg>
                                                        {{ $projectNewTaskCount }}
                                                    </button>
                                                </form>
                                                <div class="project-task-preview" role="tooltip">
                                                    <div class="project-task-preview-heading">
                                                        <span>New assignments</span>
                                                        <div class="project-task-preview-actions">
                                                            <strong>{{ $projectNewTaskCount }}</strong>
                                                            <form method="POST" action="{{ route('projects.new-tasks.read-all', $project) }}">
                                                                @csrf
                                                                <button type="submit">Mark all read</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <div class="project-task-preview-list">
                                                        @foreach($newTaskItems->take(3) as $taskItem)
                                                            @php
                                                                $task = $taskItem['task'];
                                                            @endphp
                                                            <div class="project-task-preview-item">
                                                                <form method="POST" action="{{ route('projects.new-tasks.open', $taskItem['notification']) }}" class="project-task-preview-open-form">
                                                                    @csrf
                                                                    <button type="submit" class="project-task-preview-open" aria-label="Open {{ $task->title }}">
                                                                        <span class="project-task-preview-title-row">
                                                                            <strong>{{ $task->title }}</strong>
                                                                            <span class="project-task-preview-priority project-task-preview-priority-{{ $task->priority }}">{{ ucfirst($task->priority) }}</span>
                                                                        </span>
                                                                        <span class="project-task-preview-team"><span>Team</span>{{ $task->team?->name ?? 'No team' }}</span>
                                                                        <span class="project-task-preview-meta">
                                                                            <span>{{ $task->start_date?->format('M d') ?? 'TBD' }} - {{ $task->due_date?->format('M d, Y') ?? 'No due date' }}</span>
                                                                        </span>
                                                                    </button>
                                                                </form>
                                                                <form method="POST" action="{{ route('projects.new-tasks.dismiss', $taskItem['notification']) }}" class="project-task-preview-dismiss-form">
                                                                    @csrf
                                                                    <button type="submit" class="project-task-preview-dismiss" title="Dismiss notification" aria-label="Dismiss notification for {{ $task->title }}">
                                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    @if($newTaskItems->count() > 3)
                                                        <p class="project-task-preview-more">+{{ $newTaskItems->count() - 3 }} more new task{{ $newTaskItems->count() - 3 === 1 ? '' : 's' }}</p>
                                                    @endif
                                                    <p class="project-task-preview-hint">Select a task to open it.</p>
                                                </div>
                                            </div>
                                        @endif
                                        @foreach([
                                            'overdue' => 'Overdue',
                                            'pending' => 'Pending',
                                            'in_progress' => 'In Progress',
                                            'review' => 'Review',
                                        ] as $taskStatus => $taskStatusLabel)
                                            @php
                                                $statusTaskItems = $projectTaskItems->where('status', $taskStatus);
                                            @endphp
                                            @if($statusTaskItems->isNotEmpty())
                                                <div class="project-task-indicator" tabindex="0">
                                                    <span class="project-task-count project-task-count-{{ str_replace('_', '-', $taskStatus) }}"
                                                          aria-label="{{ $statusTaskItems->count() }} {{ strtolower($taskStatusLabel) }} task{{ $statusTaskItems->count() === 1 ? '' : 's' }}">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                                            @switch($taskStatus)
                                                                @case('overdue')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 4h.01M10.3 4.3L2.6 18a2 2 0 001.7 3h15.4a2 2 0 001.7-3L13.7 4.3a2 2 0 00-3.4 0z"/>
                                                                    @break
                                                                @case('in_progress')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                                    @break
                                                                @case('review')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12zm10 2.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/>
                                                                    @break
                                                                @default
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                            @endswitch
                                                        </svg>
                                                        <strong>{{ $statusTaskItems->count() }}</strong>
                                                    </span>
                                                    <div class="project-task-preview" role="tooltip">
                                                        <div class="project-task-preview-heading">
                                                            <span>{{ $taskStatusLabel }} tasks</span>
                                                            <strong>{{ $statusTaskItems->count() }}</strong>
                                                        </div>
                                                        <div class="project-task-preview-list">
                                                            @foreach($statusTaskItems->take(3) as $taskItem)
                                                                @php
                                                                    $task = $taskItem['task'];
                                                                @endphp
                                                                <div class="project-task-preview-item">
                                                                    <div class="project-task-preview-title-row">
                                                                        <strong>{{ $task->title }}</strong>
                                                                        <span class="project-task-preview-priority project-task-preview-priority-{{ $task->priority }}">{{ ucfirst($task->priority) }}</span>
                                                                    </div>
                                                                    <div class="project-task-preview-team"><span>Team</span>{{ $task->team?->name ?? 'No team' }}</div>
                                                                    <div class="project-task-preview-meta">
                                                                        <span>{{ $task->start_date?->format('M d') ?? 'TBD' }} - {{ $task->due_date?->format('M d, Y') ?? 'No due date' }}</span>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                        @if($statusTaskItems->count() > 3)
                                                            <p class="project-task-preview-more">+{{ $statusTaskItems->count() - 3 }} more task{{ $statusTaskItems->count() - 3 === 1 ? '' : 's' }}</p>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                        <span class="project-status-pill {{ $statusClass }}" title="Project status: {{ $statusLabel }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </div>
                                </div>

                                <p class="mt-4 line-clamp-2 min-h-[2.5rem] text-sm leading-5 text-slate-600">
                                    {{ $project->description ?: 'No description provided.' }}
                                </p>

                                <div class="project-team-list-frame">
                                    <div class="project-team-list">
                                    @foreach($item['teams'] as $team)
                                        @php
                                            $teamRole = $team->pivot->role ?? 'member';
                                            $roleLabel = $teamRole === 'lead' ? 'Team Lead' : 'Member';
                                            $roleClass = $teamRole === 'lead'
                                                ? 'bg-indigo-50 text-indigo-700 border-indigo-100'
                                                : 'bg-emerald-50 text-emerald-700 border-emerald-100';
                                        @endphp
                                        <a href="{{ route('projects.open', ['project' => $project, 'team' => $team->id]) }}"
                                           class="project-team-link focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
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

                                <div class="mt-auto border-t border-slate-100 pt-4 text-sm">
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
