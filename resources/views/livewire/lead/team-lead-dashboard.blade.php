<div class="space-y-3 text-[14px]" @if(!$showEventForm && !$showMemberTasksModal) wire:poll.visible.30s @endif>

    {{-- Flash message --}}
    @if(session('event_success'))
        <x-floating-notification :message="session('event_success')" />
    @endif
{{-- ── Team tabs ─────────────────────────────────────────────────────── --}}
    @if($teams->isNotEmpty())
        <div class="flex gap-2 flex-wrap">
            @foreach($teams as $team)
                <button wire:click="selectTeam({{ $team->id }})"
                        class="px-3.5 py-2 rounded-lg text-sm font-semibold transition border
                               {{ $selectedTeamId === $team->id
                                  ? 'bg-indigo-600 text-white border-indigo-600'
                                  : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-400 hover:text-indigo-600' }}">
                    {{ $team->name }}
                    <span class="ml-1.5 text-xs opacity-70">{{ $team->project->name }}</span>
                </button>
            @endforeach
        </div>
    @endif

    @if(!$selectedTeam)
        <div class="ui-empty-state">
            <p class="text-sm font-semibold text-gray-700">You are not leading any teams yet.</p>
            <p class="mt-1 text-sm text-gray-500">Team dashboards will appear here once you are assigned as a lead.</p>
        </div>
    @else

    {{-- ── Row 1: Project summary + Progress ──────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Project summary card --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <div class="flex items-start justify-between gap-4 mb-3">
                <div>
                    <p class="text-[11px] font-semibold text-indigo-500 uppercase tracking-wider mb-1">
                        {{ $selectedTeam->name }}
                    </p>
                    <h2 class="text-xl font-bold text-gray-900 leading-tight">{{ $project->name }}</h2>
                    @if($project->description)
                        <p class="text-xs text-gray-500 mt-1 line-clamp-1">{{ $project->description }}</p>
                    @endif
                </div>
                @php
                    $statusBadge = match($project->status) {
                        'active'    => 'bg-green-100 text-green-700',
                        'on_hold'   => 'bg-yellow-100 text-yellow-700',
                        'completed' => 'bg-blue-100 text-blue-700',
                        default     => 'bg-gray-100 text-gray-500',
                    };
                @endphp
                <span class="inline-flex flex-shrink-0 px-3 py-1 rounded-full text-xs font-semibold {{ $statusBadge }}">
                    {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                </span>
            </div>

            {{-- Date range + days remaining --}}
            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 mb-4">
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    {{ $project->start_date->format('M d, Y') }} — {{ $project->end_date->format('M d, Y') }}
                </span>
                @if($daysRemaining !== null)
                    <span class="flex items-center gap-1.5
                                 {{ $daysRemaining < 0 ? 'text-red-500 font-semibold' : ($daysRemaining <= 7 ? 'text-orange-500 font-semibold' : '') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        @if($daysRemaining < 0)
                            {{ abs($daysRemaining) }} day{{ abs($daysRemaining) !== 1 ? 's' : '' }} overdue
                        @elseif($daysRemaining === 0)
                            Due today
                        @else
                            {{ $daysRemaining }} day{{ $daysRemaining !== 1 ? 's' : '' }} remaining
                        @endif
                    </span>
                @endif
            </div>

            {{-- Stat chips --}}
            @if($stats)
                <div class="grid grid-cols-3 sm:grid-cols-7 gap-2">
                    <div class="bg-gray-50 rounded-lg px-2 py-2.5 text-center">
                        <p class="text-lg font-bold text-gray-800 leading-none">{{ $stats['total'] }}</p>
                        <p class="text-[11px] text-gray-400 mt-1">Total</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg px-2 py-2.5 text-center">
                        <p class="text-lg font-bold text-gray-600 leading-none">{{ $stats['pending'] }}</p>
                        <p class="text-[11px] text-gray-400 mt-1">Pending</p>
                    </div>
                    <div class="bg-blue-50 rounded-lg px-2 py-2.5 text-center">
                        <p class="text-lg font-bold text-blue-700 leading-none">{{ $stats['inProgress'] }}</p>
                        <p class="text-[11px] text-blue-500 mt-1">In Progress</p>
                    </div>
                    <div class="bg-amber-50 rounded-lg px-2 py-2.5 text-center">
                        <p class="text-lg font-bold text-amber-800 leading-none">{{ $stats['review'] }}</p>
                        <p class="text-[11px] text-amber-600 mt-1">Review</p>
                    </div>
                    <div class="bg-green-50 rounded-lg px-2 py-2.5 text-center">
                        <p class="text-lg font-bold text-green-700 leading-none">{{ $stats['done'] }}</p>
                        <p class="text-[11px] text-green-500 mt-1">Done</p>
                    </div>
                    <div class="bg-red-50 rounded-lg px-2 py-2.5 text-center">
                        <p class="text-lg font-bold text-red-600 leading-none">{{ $stats['overdue'] }}</p>
                        <p class="text-[11px] text-red-400 mt-1">Overdue</p>
                    </div>
                    <div class="bg-indigo-50 rounded-lg px-2 py-2.5 text-center">
                        <p class="text-lg font-bold text-indigo-700 leading-none">{{ $stats['members'] }}</p>
                        <p class="text-[11px] text-indigo-400 mt-1">Members</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Progress card --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex flex-col justify-between">
            <div>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Team Progress</h3>
                <p class="text-4xl font-extrabold text-indigo-600 leading-none mb-1">
                    {{ $progressPct }}<span class="text-xl font-bold text-indigo-400">%</span>
                </p>
                <p class="text-xs text-gray-400">
                    {{ $stats['done'] ?? 0 }} of {{ $stats['total'] ?? 0 }} tasks completed
                </p>
            </div>

            {{-- Circular-style progress bar (stacked bar) --}}
            <div class="mt-4">
                @if(($stats['total'] ?? 0) > 0)
                    <div class="flex h-3 rounded-full overflow-hidden gap-px bg-gray-100">
                        @if($stats['done'] > 0)
                            <div class="bg-green-500 transition-all duration-700"
                                 style="width: {{ round(($stats['done'] / $stats['total']) * 100) }}%"
                                 title="Done: {{ $stats['done'] }}"></div>
                        @endif
                        @if($stats['inProgress'] > 0)
                            <div class="bg-blue-400 transition-all duration-700"
                                 style="width: {{ round(($stats['inProgress'] / $stats['total']) * 100) }}%"
                                 title="In Progress: {{ $stats['inProgress'] }}"></div>
                        @endif
                        @if(($stats['review'] ?? 0) > 0)
                            <div class="bg-amber-400 transition-all duration-700"
                                 style="width: {{ round((($stats['review'] ?? 0) / $stats['total']) * 100) }}%"
                                 title="Review: {{ $stats['review'] ?? 0 }}"></div>
                        @endif
                        @if($stats['pending'] > 0)
                            <div class="bg-gray-200 transition-all duration-700"
                                 style="width: {{ round(($stats['pending'] / $stats['total']) * 100) }}%"
                                 title="Pending: {{ $stats['pending'] }}"></div>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 mt-3 text-[11px] text-gray-500">
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>Done</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>In Progress</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>Review</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-gray-200 border border-gray-300"></span>Pending</span>
                    </div>
                @else
                    <div class="h-3 bg-gray-100 rounded-full"></div>
                    <p class="text-xs text-gray-400 mt-2">No tasks assigned yet.</p>
                @endif
            </div>

            {{-- Priority breakdown --}}
            @if($tasksByPriority->isNotEmpty())
                <div class="mt-4 pt-4 border-t border-gray-100 space-y-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">By Priority</p>
                    @foreach(['high' => ['bg-red-500','text-red-600'], 'medium' => ['bg-yellow-400','text-yellow-600'], 'low' => ['bg-gray-300','text-gray-500']] as $priority => [$bar, $text])
                        @php $count = $tasksByPriority->get($priority, collect())->count(); @endphp
                        @if($count > 0)
                            <div class="flex items-center gap-2">
                                <span class="w-16 text-xs {{ $text }} font-medium capitalize">{{ $priority }}</span>
                                <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="{{ $bar }} h-2 rounded-full transition-all duration-500"
                                         style="width: {{ $stats['total'] > 0 ? round(($count / $stats['total']) * 100) : 0 }}%"></div>
                                </div>
                                <span class="text-xs text-gray-400 w-5 text-right">{{ $count }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ── Row 2: Timeline + Team members ─────────────────────────────────── --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

        {{-- Timeline graph (takes 2/3) --}}
        <div class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col min-h-[430px]">
            <div class="flex items-center justify-between px-5 py-2.5 border-b border-gray-100">
                <button wire:click="previousMonth"
                        class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 transition"
                        aria-label="Previous month">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>

                <div class="flex items-center gap-2.5">
                    <h3 class="text-base font-semibold text-gray-800">{{ $monthLabel }}</h3>
                    <button wire:click="goToToday"
                            class="px-2.5 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition">
                        Today
                    </button>
                </div>

                <button wire:click="nextMonth"
                        class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 transition"
                        aria-label="Next month">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-3">
                <h3 class="text-sm font-semibold text-gray-800">Project Timeline</h3>
                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach([
                        'project' => ['label' => 'Project', 'class' => 'bg-indigo-600'],
                        'task' => ['label' => 'Tasks', 'class' => 'bg-amber-500'],
                        'actual' => ['label' => 'Start to End', 'class' => 'bg-emerald-500'],
                    ] as $kind => $meta)
                        @php $activeKind = in_array($kind, $timelineKinds, true); @endphp
                        <button type="button"
                                wire:click="toggleTimelineKind('{{ $kind }}')"
                                class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[11px] font-semibold transition
                                       {{ $activeKind ? 'border-gray-200 bg-white text-gray-700 shadow-sm' : 'border-gray-100 bg-gray-50 text-gray-400' }}">
                            <span class="h-2 w-2 rounded-full {{ $activeKind ? $meta['class'] : 'bg-gray-300' }}"></span>
                            {{ $meta['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="flex-1 px-3.5 py-3">
                @if($timelineGraph['rows']->isEmpty())
                    <div class="py-12 text-center text-sm text-gray-400">
                        No project, task, or actual work dates in this month.
                    </div>
                @else
                    <div class="w-full overflow-x-auto pb-2">
                        <div class="min-w-[960px]">
                            <div class="border-b border-gray-100 bg-white pb-2.5 pt-1">
                                <div class="grid h-8"
                                     style="grid-template-columns: repeat({{ $timelineGraph['totalDays'] }}, minmax(0, 1fr));">
                                    @foreach($timelineGraph['ticks'] as $tick)
                                        <div class="text-center text-[10px] leading-tight {{ ($timelineGraph['todayDay'] ?? null) === $tick['day'] ? 'font-bold text-rose-600' : ($tick['major'] ? 'font-semibold text-gray-700' : 'font-medium text-gray-400') }}">
                                            <span class="block">{{ $tick['day'] }}</span>
                                            <span class="block">{{ substr($tick['weekday'], 0, 1) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="max-h-[calc(100vh-430px)] min-h-[230px] overflow-y-auto pr-2">
                                <div class="divide-y divide-gray-100">
                                    @foreach($timelineGraph['rows'] as $row)
                                        <x-timeline-bar
                                            :row="$row"
                                            :ticks="$timelineGraph['ticks']"
                                            :today-day="$timelineGraph['todayDay'] ?? null"
                                            :total-days="$timelineGraph['totalDays']"
                                        />
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3 border-t border-gray-100 bg-gray-50 text-xs text-gray-500">
                <span class="flex items-center gap-1.5">
                    <span class="w-4 h-3 rounded bg-indigo-600"></span> Project timeline
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-4 h-3 rounded bg-amber-500"></span> Task
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-4 h-3 rounded bg-emerald-500"></span> Start to End
                </span>
            </div>
        </div>

        {{-- Legacy event timeline UI is intentionally parked while the graph-based Project Timeline is active.
             It still references $events, $memberStartActivities, and event CRUD methods such as
             openCreateEvent/openEditEvent; restore this block only if the lead dashboard needs the
             editable event-list timeline instead of the current graph. --}}
        @if(false)
        {{-- Timeline (takes 2/3) --}}
        <div class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h3 class="text-sm font-semibold text-gray-700">Project Timeline</h3>
                    <span class="text-xs text-gray-400">
                        {{ $events->count() }} event{{ $events->count() !== 1 ? 's' : '' }}
                        •
                        {{ $memberStartActivities->count() }} member start{{ $memberStartActivities->count() !== 1 ? 's' : '' }}
                    </span>
                </div>
                @if(!$showEventForm)
                    <button wire:click="openCreateEvent"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Event
                    </button>
                @endif
            </div>

            {{-- ── Event form ──────────────────────────────────────────────── --}}
            @if($showEventForm)
                <div class="px-6 py-5 border-b border-gray-100 bg-gray-50">
                    <h4 class="text-sm font-semibold text-gray-700 mb-4">
                        {{ $editingEventId ? 'Edit Event' : 'New Timeline Event' }}
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Title --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Title <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="eventTitle" type="text" placeholder="e.g. Design phase complete"
                                   class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('eventTitle') border-red-400 @enderror">
                            @error('eventTitle') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        {{-- Date --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Date <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="eventDate" type="date"
                                   class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('eventDate') border-red-400 @enderror">
                            @error('eventDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        {{-- Type --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                            <select wire:model="eventType"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="update">Update</option>
                                <option value="milestone">Milestone</option>
                                <option value="deadline">Deadline</option>
                            </select>
                        </div>

                        {{-- Description --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                            <textarea wire:model="eventDescription" rows="2" placeholder="Optional notes…"
                                      class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-4">
                        <button wire:click="saveEvent"
                                wire:loading.attr="disabled"
                                wire:target="saveEvent"
                                class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition disabled:opacity-60 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="saveEvent">{{ $editingEventId ? 'Update Event' : 'Add to Timeline' }}</span>
                            <span wire:loading wire:target="saveEvent">Saving...</span>
                        </button>
                        <button wire:click="cancelEventForm"
                                wire:loading.attr="disabled"
                                wire:target="cancelEventForm,saveEvent"
                                class="px-4 py-2 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition disabled:opacity-60 disabled:cursor-not-allowed">
                            Cancel
                        </button>
                    </div>
                </div>
            @endif

            @if($events->isEmpty() && $memberStartActivities->isEmpty() && !$showEventForm)
                <div class="ui-empty-state rounded-none border-0 shadow-none">
                    <svg class="w-10 h-10 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm mb-3">No events on the timeline yet.</p>
                    <button wire:click="openCreateEvent"
                            wire:loading.attr="disabled"
                            wire:target="openCreateEvent"
                            class="px-4 py-2 text-xs font-medium text-indigo-600 border border-indigo-200 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition disabled:opacity-60 disabled:cursor-not-allowed">
                        Add the first event
                    </button>
                </div>
            @else
                @if($events->isNotEmpty())
                    <div class="px-6 py-5">
                        <ol class="relative border-l-2 border-gray-200 space-y-0">
                            @foreach($events as $index => $event)
                                @php
                                    $typeConfig = match($event->type) {
                                        'milestone' => [
                                            'dot'    => 'bg-indigo-600 ring-indigo-200',
                                            'badge'  => 'bg-indigo-100 text-indigo-700',
                                            'label'  => 'Milestone',
                                        ],
                                        'deadline' => [
                                            'dot'    => 'bg-red-500 ring-red-200',
                                            'badge'  => 'bg-red-100 text-red-700',
                                            'label'  => 'Deadline',
                                        ],
                                        default => [
                                            'dot'    => 'bg-emerald-500 ring-emerald-200',
                                            'badge'  => 'bg-emerald-100 text-emerald-700',
                                            'label'  => 'Update',
                                        ],
                                    };
                                @endphp
                                <li class="mb-0 ml-6 pb-7 last:pb-0 relative
                                           {{ $event->is_past && !$event->is_today ? 'opacity-60' : '' }}">

                                    {{-- Dot on the line --}}
                                    <span class="absolute -left-[1.85rem] flex items-center justify-center w-6 h-6 rounded-full
                                                 ring-4 {{ $typeConfig['dot'] }} ring-white top-0.5">
                                        @if($event->type === 'milestone')
                                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        @elseif($event->type === 'deadline')
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4m0 4h.01"/>
                                            </svg>
                                        @else
                                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        @endif
                                    </span>

                                    {{-- Content --}}
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap mb-0.5">
                                                <p class="text-sm font-semibold text-gray-900">{{ $event->title }}</p>
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $typeConfig['badge'] }}">
                                                    {{ $typeConfig['label'] }}
                                                </span>
                                                @if($event->is_today)
                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                                                        Today
                                                    </span>
                                                @endif
                                            </div>
                                            @if($event->description)
                                                <p class="text-xs text-gray-500 mt-0.5">{{ $event->description }}</p>
                                            @endif
                                            {{-- Edit / delete --}}
                                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                                <button wire:click="openEditEvent({{ $event->id }})"
                                                        class="ui-action-button ui-action-primary">
                                                    Edit
                                                </button>
                                                <button wire:click="confirmDeleteEvent({{ $event->id }})"
                                                        class="ui-action-button ui-action-danger">
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <p class="text-sm font-semibold {{ $event->is_past && !$event->is_today ? 'text-gray-400' : 'text-gray-700' }}">
                                                {{ $event->event_date->format('M d, Y') }}
                                            </p>
                                            <p class="text-xs mt-0.5
                                                       {{ $event->days_diff < 0 ? 'text-red-400' : ($event->days_diff <= 7 && !$event->is_past ? 'text-orange-500 font-medium' : 'text-gray-400') }}">
                                                @if($event->is_today)
                                                    Today
                                                @elseif($event->days_diff < 0)
                                                    {{ abs($event->days_diff) }}d ago
                                                @else
                                                    in {{ $event->days_diff }}d
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif

                @if($memberStartActivities->isNotEmpty())
                    <div class="px-6 py-5 border-t border-gray-100">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Member Task Start Activity</h4>
                        <ol class="relative border-l-2 border-gray-200 space-y-0">
                            @foreach($memberStartActivities as $task)
                                <li class="mb-0 ml-6 pb-6 last:pb-0 relative">
                                    <span class="absolute -left-[1.85rem] flex items-center justify-center w-6 h-6 rounded-full ring-4 bg-blue-500 ring-white top-0.5">
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6l4 2"/>
                                        </svg>
                                    </span>

                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap mb-0.5">
                                                <p class="text-sm font-semibold text-gray-900">
                                                    {{ $task->assignee?->name ?? 'Unassigned' }} started {{ $task->title }}
                                                </p>
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                                    Task Started
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                Team {{ $task->team->name }} • Due {{ $task->due_date ? $task->due_date->format('M d, Y') : '—' }}
                                            </p>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <p class="text-sm font-semibold text-gray-700">{{ $task->start_date->format('M d, Y') }}</p>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            @endif
        </div>

        @endif

        {{-- Team members sidebar --}}
        <div class="space-y-3">

            {{-- Members list --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Team Members</h3>
                    <span class="text-xs text-gray-400">{{ $stats['members'] ?? 0 }} member{{ ($stats['members'] ?? 0) !== 1 ? 's' : '' }}</span>
                </div>

                @if($selectedTeam->members->isEmpty())
                    <div class="px-4 py-7 text-center text-gray-400 text-xs">
                        No members assigned yet.
                    </div>
                @else
                    <ul class="divide-y divide-gray-50">
                        @foreach($selectedTeam->members as $member)
                            @php
                                $memberTasks = $memberTasksMap->get($member->id, collect());
                                $memberDone  = $memberTasks->where('status', 'done')->count();
                                $memberTotal = $memberTasks->count();
                                $memberPct   = $memberTotal > 0 ? (int) round(($memberDone / $memberTotal) * 100) : 0;
                            @endphp
                            <li class="relative">
                                <button type="button"
                                        wire:click="openMemberTasks({{ $member->id }})"
                                        class="w-full px-4 py-2.5 text-left flex items-center gap-3 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-inset transition cursor-pointer">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-bold flex-shrink-0">
                                        {{ strtoupper(substr($member->name, 0, 1)) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-800 truncate">{{ $member->name }}</p>
                                        @if($member->pivot?->notes)
                                            <p class="mt-0.5 truncate text-xs text-gray-400">{{ $member->pivot->notes }}</p>
                                        @endif
                                        <div class="flex items-center gap-2 mt-1">
                                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                <div class="h-1.5 bg-indigo-500 rounded-full transition-all duration-500"
                                                     style="width: {{ $memberPct }}%"></div>
                                            </div>
                                            <span class="text-xs text-gray-400 flex-shrink-0">{{ $memberDone }}/{{ $memberTotal }}</span>
                                        </div>
                                    </div>
                                    <span class="text-xs text-indigo-500 flex-shrink-0 hidden sm:inline">View tasks</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if(false)
            {{-- Assigned tasks by member --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Assigned Tasks by Member</h3>
                </div>

                @if($selectedTeam->members->isEmpty())
                    <div class="px-5 py-8 text-center text-gray-400 text-xs">
                        No members assigned yet.
                    </div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($selectedTeam->members as $member)
                            @php $assignedTasks = $memberTasksMap->get($member->id, collect()); @endphp
                            <div class="px-5 py-4">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ $member->name }}</p>
                                    <span class="text-xs font-medium text-gray-500 bg-gray-100 rounded-full px-2 py-0.5">
                                        {{ $assignedTasks->count() }} task{{ $assignedTasks->count() !== 1 ? 's' : '' }}
                                    </span>
                                </div>

                                @if($assignedTasks->isEmpty())
                                    <p class="text-xs text-gray-400">No tasks assigned.</p>
                                @else
                                    <ul class="space-y-2">
                                        @foreach($assignedTasks as $task)
                                            @php
                                                $statusLabel = match($task->status) {
                                                    'pending'     => 'Pending',
                                                    'in_progress' => 'In Progress',
                                                    'review'      => 'Review',
                                                    'done'        => 'Done',
                                                    default       => ucfirst($task->status),
                                                };

                                                $statusClass = match($task->status) {
                                                    'pending'     => 'bg-gray-100 text-gray-600',
                                                    'in_progress' => 'bg-blue-100 text-blue-700',
                                                    'review'      => 'bg-amber-100 text-amber-800',
                                                    'done'        => 'bg-green-100 text-green-700',
                                                    default       => 'bg-gray-100 text-gray-500',
                                                };
                                            @endphp
                                            <li class="p-2 rounded-lg border border-gray-100 bg-gray-50">
                                                <div class="flex items-start justify-between gap-2">
                                                    <p class="text-xs font-medium text-gray-700 leading-5">{{ $task->title }}</p>
                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap {{ $statusClass }}">
                                                        {{ $statusLabel }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-3 text-[11px] text-gray-400 mt-1">
                                                    <p>
                                                        Start
                                                        {{ $task->start_date ? $task->start_date->format('M d, Y') : 'Not set' }}
                                                    </p>
                                                    <p>Due {{ $task->due_date ? $task->due_date->format('M d, Y') : '—' }}</p>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @endif

            @if($atRiskTasks->isNotEmpty())
                <div class="bg-amber-50 rounded-xl border border-amber-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-amber-200 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <h3 class="text-sm font-semibold text-amber-800">At Risk</h3>
                        </div>
                        <span class="text-xs font-semibold text-amber-700">{{ $atRiskTasks->count() }}</span>
                    </div>
                    <ul class="divide-y divide-amber-100">
                        @foreach($atRiskTasks as $task)
                            @php $daysLeft = (int) now()->startOfDay()->diffInDays($task->due_date, false); @endphp
                            <li class="px-4 py-2.5">
                                <p class="text-sm font-medium text-amber-950 truncate">{{ $task->title }}</p>
                                <div class="flex items-center justify-between mt-0.5">
                                    <p class="text-xs text-amber-700">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</p>
                                    <p class="text-xs text-amber-600">
                                        {{ $daysLeft === 0 ? 'Due today' : 'Due in '.$daysLeft.'d' }}
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Recent overdue tasks warning --}}
            @php
                $overdueTasks = $selectedTeam->tasks
                    ->filter(fn($t) => $t->isExceededDeadline())
                    ->sortBy('due_date')
                    ->take(5);
            @endphp
            @if($overdueTasks->isNotEmpty())
                <div class="bg-red-50 rounded-xl border border-red-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-red-200 flex items-center gap-2">
                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        <h3 class="text-sm font-semibold text-red-700">Overdue Tasks</h3>
                    </div>
                    <ul class="divide-y divide-red-100">
                        @foreach($overdueTasks as $task)
                            <li class="px-4 py-2.5">
                                <p class="text-sm font-medium text-red-800 truncate">{{ $task->title }}</p>
                                <div class="flex items-center justify-between mt-0.5">
                                    <p class="text-xs text-red-500">{{ $task->assignee?->name ?? '-' }}</p>
                                    <p class="text-xs text-red-400">Due {{ $task->due_date ? $task->due_date->format('M d') : '—' }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
    @endif

    </div>

    {{-- Member assigned tasks modal --}}
    @if($showMemberTasksModal && $modalMember)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
             role="dialog"
             aria-modal="true"
             aria-labelledby="member-tasks-modal-title">
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"
                 wire:click="closeMemberTasksModal"></div>
            <div class="relative w-full max-w-lg max-h-[85vh] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl flex flex-col"
                 @click.stop>
                <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4">
                    <div>
                        <h3 id="member-tasks-modal-title" class="text-base font-semibold text-gray-900">
                            Tasks for {{ $modalMember->name }}
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $modalMemberTasks->count() }} assigned in this team
                        </p>
                    </div>
                    <button type="button"
                            wire:click="closeMemberTasksModal"
                            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="overflow-y-auto px-5 py-4">
                    @if($modalMemberTasks->isEmpty())
                        <p class="text-sm text-gray-500 text-center py-8">No tasks assigned to this member on this team yet.</p>
                    @else
                        <ul class="space-y-3">
                            @foreach($modalMemberTasks as $task)
                                @php
                                    $statusLabel = match($task->status) {
                                        'pending'     => 'Pending',
                                        'in_progress' => 'In progress',
                                        'review'      => 'Review',
                                        'done'        => 'Done',
                                        default       => ucfirst($task->status),
                                    };
                                    $statusClass = match($task->status) {
                                        'pending'     => 'bg-gray-100 text-gray-600',
                                        'in_progress' => 'bg-blue-100 text-blue-700',
                                        'review'      => 'bg-amber-100 text-amber-800',
                                        'done'        => 'bg-green-100 text-green-700',
                                        default       => 'bg-gray-100 text-gray-500',
                                    };
                                    $memberProgress = $task->memberProgress->firstWhere('user_id', $modalMember->id);
                                    $memberLogs = $task->journalLogs->where('user_id', $modalMember->id)->sortBy('log_date');
                                    $actualStart = collect([
                                        optional($memberLogs->first())->log_date,
                                        $memberProgress?->started_at,
                                    ])
                                        ->filter()
                                        ->map(fn ($date) => \Carbon\Carbon::parse($date))
                                        ->sortBy(fn ($date) => $date->timestamp)
                                        ->first();
                                    $startedEarly = $actualStart && $task->start_date && $actualStart->lt($task->start_date->copy()->startOfDay());
                                @endphp
                                <li class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-medium text-gray-800">{{ $task->title }}</p>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap {{ $statusClass }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </div>
                                    @if($task->description)
                                        <p class="text-xs text-gray-500 mt-1.5 line-clamp-2">{{ $task->description }}</p>
                                    @endif
                                    <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-gray-500 sm:grid-cols-2">
                                        <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-gray-100">
                                            <p class="font-semibold text-gray-400">Scheduled start</p>
                                            <p class="mt-0.5 text-gray-700">{{ $task->start_date?->format('M d, Y') ?? 'Not set' }}</p>
                                        </div>
                                        <div class="rounded-lg px-3 py-2 ring-1 {{ $startedEarly ? 'bg-emerald-50/60 ring-emerald-200' : 'bg-white ring-gray-100' }}">
                                            <p class="font-semibold {{ $startedEarly ? 'text-emerald-600' : 'text-gray-400' }}">Actual start</p>
                                            <p class="mt-0.5 {{ $startedEarly ? 'font-semibold text-emerald-700' : 'text-gray-700' }}">
                                                {{ $actualStart ? $actualStart->format('M d, Y') : 'Not started' }}
                                            </p>
                                            @if($startedEarly)
                                                <p class="mt-0.5 text-[11px] font-medium text-emerald-600">Started early</p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-2 grid grid-cols-1 gap-2 text-xs text-gray-500 sm:grid-cols-2">
                                        <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-gray-100">
                                            <p class="font-semibold text-gray-400">Due date</p>
                                            <p class="mt-0.5 text-gray-700">{{ $task->due_date?->format('M d, Y') ?? 'Not set' }}</p>
                                        </div>
                                        <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-gray-100">
                                            <p class="font-semibold text-gray-400">Priority</p>
                                            <p class="mt-0.5 text-gray-700">{{ $task->priority ? ucfirst($task->priority) : 'Not set' }}</p>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <x-confirmation-modal wire:model="confirmingDeleteEvent" maxWidth="md">
        <x-slot name="title">
            Remove timeline event?
        </x-slot>

        <x-slot name="content">
            This event will be removed from the project timeline.
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="cancelDeleteEvent" wire:loading.attr="disabled">
                Cancel
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="deleteEventConfirmed" wire:loading.attr="disabled">
                Remove
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>

    @endif
</div>



