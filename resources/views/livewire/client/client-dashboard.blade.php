<div class="space-y-6" wire:poll.visible.15s>

    {{-- ── Project tabs ──────────────────────────────────────────────────── --}}
    @if($projects->isNotEmpty())
        <div class="flex gap-2 flex-wrap">
            @foreach($projects as $project)
                <button wire:click="selectProject({{ $project->id }})"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition border
                               {{ $selectedProjectId === $project->id
                                  ? 'bg-indigo-600 text-white border-indigo-600'
                                  : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-400 hover:text-indigo-600' }}">
                    {{ $project->name }}
                </button>
            @endforeach
        </div>
    @endif

    @if(!$selectedProject)
        <div class="bg-white rounded-xl border border-gray-200 py-20 text-center text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="text-sm">No projects assigned to your account yet.</p>
        </div>
    @else

        {{-- ── Project header card ──────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">{{ $selectedProject->name }}</h2>
                        @php
                            $badge = match($selectedProject->status) {
                                'active'    => 'bg-green-100 text-green-700',
                                'on_hold'   => 'bg-yellow-100 text-yellow-700',
                                'completed' => 'bg-blue-100 text-blue-700',
                                default     => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $badge }}">
                            {{ ucfirst(str_replace('_', ' ', $selectedProject->status)) }}
                        </span>
                    </div>
                    @if($selectedProject->description)
                        <p class="text-sm text-gray-500">{{ $selectedProject->description }}</p>
                    @endif
                    <p class="mt-1 text-xs text-gray-400">
                        {{ $selectedProject->start_date->format('M d, Y') }}
                        &rarr;
                        {{ $selectedProject->end_date->format('M d, Y') }}
                    </p>
                </div>

                {{-- Overall progress --}}
                @if($stats)
                    <div class="flex-shrink-0 text-right">
                        @php $pct = $selectedProject->completionPercentage() @endphp
                        <p class="text-3xl font-bold text-indigo-600">{{ $pct }}%</p>
                        <p class="text-xs text-gray-400 mt-0.5">Overall completion</p>
                    </div>
                @endif
            </div>

            {{-- Progress bar --}}
            @if($stats)
                <div class="mt-4">
                    <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-3 rounded-full transition-all duration-500"
                             style="width: {{ $selectedProject->completionPercentage() }}%;
                                    background: linear-gradient(90deg, #6366f1, #818cf8);">
                        </div>
                    </div>
                </div>

                {{-- Stats row --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                    <div class="bg-gray-50 rounded-lg px-4 py-3 text-center">
                        <p class="text-2xl font-bold text-gray-800">{{ $stats['totalTasks'] }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Total Tasks</p>
                    </div>
                    <div class="bg-green-50 rounded-lg px-4 py-3 text-center">
                        <p class="text-2xl font-bold text-green-700">{{ $stats['doneTasks'] }}</p>
                        <p class="text-xs text-green-500 mt-0.5">Completed</p>
                    </div>
                    <div class="bg-blue-50 rounded-lg px-4 py-3 text-center">
                        <p class="text-2xl font-bold text-blue-700">{{ $stats['pendingTasks'] }}</p>
                        <p class="text-xs text-blue-500 mt-0.5">Active tasks</p>
                    </div>
                    <div class="bg-red-50 rounded-lg px-4 py-3 text-center">
                        <p class="text-2xl font-bold text-red-600">{{ $stats['overdueTasks'] }}</p>
                        <p class="text-xs text-red-400 mt-0.5">Overdue</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- ── Calendar + Upcoming events ───────────────────────────────────── --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            {{-- Calendar (takes 2/3 width on xl) --}}
            <div class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

                {{-- Calendar nav --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <button wire:click="previousMonth"
                            class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <div class="flex items-center gap-3">
                        <h3 class="text-base font-semibold text-gray-800">{{ $monthLabel }}</h3>
                        <button wire:click="goToToday"
                                class="px-3 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition">
                            Today
                        </button>
                    </div>
                    <button wire:click="nextMonth"
                            class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>

                {{-- Day-of-week headers --}}
                <div class="grid grid-cols-7 border-b border-gray-100">
                    @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dayName)
                        <div class="py-2 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            {{ $dayName }}
                        </div>
                    @endforeach
                </div>

                {{-- Calendar grid --}}
                <div>
                    @foreach($calendarGrid as $week)
                        <div class="grid grid-cols-7 border-b border-gray-50 last:border-b-0">
                            @foreach($week as $cell)
                                @if($cell === null)
                                    <div class="min-h-[80px] p-2 bg-gray-50/50"></div>
                                @else
                                    <div class="min-h-[80px] p-2 border-r border-gray-50 last:border-r-0
                                                {{ $cell['today'] ? 'bg-indigo-50' : 'hover:bg-gray-50' }} transition">
                                        {{-- Day number --}}
                                        <span class="inline-flex w-7 h-7 items-center justify-center rounded-full text-sm
                                                     {{ $cell['today']
                                                        ? 'bg-indigo-600 text-white font-bold'
                                                        : 'text-gray-700 font-medium' }}">
                                            {{ $cell['day'] }}
                                        </span>

                                        {{-- Events + tasks on this day --}}
                                        @if($cell['items']->isNotEmpty())
                                            <div class="mt-1 space-y-0.5">
                                                @foreach($cell['items'] as $item)
                                                    @php
                                                        $dot = match (true) {
                                                            ($item['kind'] ?? '') === 'task' && ($item['variant'] ?? '') === 'task_start'
                                                                => 'bg-slate-500 text-white',
                                                            ($item['kind'] ?? '') === 'task'
                                                                => 'bg-amber-500 text-white',
                                                            ($item['type'] ?? '') === 'milestone' => 'bg-indigo-500 text-white',
                                                            ($item['type'] ?? '') === 'deadline'  => 'bg-red-500 text-white',
                                                            default => 'bg-emerald-500 text-white',
                                                        };
                                                    @endphp
                                                    <div class="flex items-center gap-1 px-1.5 py-0.5 rounded text-xs {{ $dot }} truncate"
                                                         title="{{ $item['title'] }}">
                                                        <span class="truncate">{{ $item['title'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>

                {{-- Legend --}}
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-6 py-3 border-t border-gray-100 bg-gray-50 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded bg-indigo-500"></span> Milestone
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded bg-red-500"></span> Deadline
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded bg-emerald-500"></span> Update
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded bg-amber-500"></span> Task due
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded bg-slate-500"></span> Task start
                    </span>
                </div>
            </div>

            {{-- Upcoming items sidebar (events + task due dates) --}}
            <div class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-700">Upcoming</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Events and task due dates</p>
                    </div>

                    @if($upcomingItems->isEmpty())
                        <div class="px-5 py-10 text-center text-gray-400">
                            <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-xs">No upcoming events or task deadlines.</p>
                        </div>
                    @else
                        <ul class="divide-y divide-gray-50">
                            @foreach($upcomingItems as $row)
                                @php
                                    $isTask = ($row['kind'] ?? '') === 'task';
                                    $typeKey = $isTask ? 'task_due' : ($row['type'] ?? 'update');
                                    $typeColor = match($typeKey) {
                                        'milestone' => 'border-indigo-400 bg-indigo-50',
                                        'deadline'  => 'border-red-400 bg-red-50',
                                        'task_due'  => 'border-amber-400 bg-amber-50',
                                        default     => 'border-emerald-400 bg-emerald-50',
                                    };
                                    $typeLabelClass = match($typeKey) {
                                        'milestone' => 'text-indigo-600',
                                        'deadline'  => 'text-red-600',
                                        'task_due'  => 'text-amber-700',
                                        default     => 'text-emerald-600',
                                    };
                                    $typeText = $isTask
                                        ? 'Task due ('.str_replace('_', ' ', $row['status'] ?? '').')'
                                        : ucfirst(str_replace('_', ' ', $row['type'] ?? 'event'));
                                    $when = $row['date'];
                                    $daysAway = now()->startOfDay()->diffInDays($when, false);
                                @endphp
                                <li class="flex gap-3 px-5 py-4">
                                    <div class="flex-shrink-0 w-1 rounded-full {{ $typeColor }} border-l-2 self-stretch"></div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-800 truncate">{{ $row['title'] }}</p>
                                        <p class="text-xs {{ $typeLabelClass }} mt-0.5">
                                            {{ $typeText }}
                                        </p>
                                        @if(!empty($row['description']))
                                            <p class="text-xs text-gray-400 mt-0.5 line-clamp-2">{{ $row['description'] }}</p>
                                        @endif
                                    </div>
                                    <div class="flex-shrink-0 text-right">
                                        <p class="text-sm font-semibold text-gray-700">
                                            {{ $when->format('M d') }}
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            @if($daysAway === 0)
                                                Today
                                            @elseif($daysAway === 1)
                                                Tomorrow
                                            @else
                                                in {{ $daysAway }}d
                                            @endif
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Teams on this project --}}
                @if($selectedProject->teams->isNotEmpty())
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-700">Teams</h3>
                        </div>
                        <ul class="divide-y divide-gray-50">
                            @foreach($selectedProject->teams as $team)
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                        {{ strtoupper(substr($team->name, 0, 1)) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-800">{{ $team->name }}</p>
                                        <p class="text-xs text-gray-400">Lead: {{ $team->lead->name }}</p>
                                    </div>
                                    @php
                                        $teamPct = $team->completionPercentage();
                                    @endphp
                                    <div class="flex-shrink-0 text-right">
                                        <p class="text-sm font-semibold text-indigo-600">{{ $teamPct }}%</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

        </div>
    @endif
</div>
