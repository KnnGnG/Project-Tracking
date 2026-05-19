<div class="space-y-6" wire:poll.visible.10s>

    {{-- ── Flash message ─────────────────────────────────────────────────────── --}}
    @if($flash)
        <x-floating-notification :message="$flash" dismiss="dismissFlash" />
    @endif

    {{-- ── Summary stat cards ─────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">

        {{-- Pending --}}
        <button wire:click="setTab('pending')"
                class="rounded-xl border px-5 py-4 text-left transition
                       {{ $activeTab === 'pending'
                          ? 'bg-gray-700 border-gray-700 shadow-sm'
                          : 'bg-white border-gray-200 hover:border-gray-300' }}">
            <p class="text-2xl font-extrabold {{ $activeTab === 'pending' ? 'text-white' : 'text-gray-800' }}">
                {{ $counts['pending'] }}
            </p>
            <p class="text-xs mt-0.5 font-medium {{ $activeTab === 'pending' ? 'text-gray-300' : 'text-gray-400' }}">
                Pending
            </p>
        </button>

        {{-- In Progress --}}
        <button wire:click="setTab('in_progress')"
                class="rounded-xl border px-5 py-4 text-left transition
                       {{ $activeTab === 'in_progress'
                          ? 'bg-blue-600 border-blue-600 shadow-sm'
                          : 'bg-white border-gray-200 hover:border-blue-300' }}">
            <p class="text-2xl font-extrabold {{ $activeTab === 'in_progress' ? 'text-white' : 'text-blue-700' }}">
                {{ $counts['in_progress'] }}
            </p>
            <p class="text-xs mt-0.5 font-medium {{ $activeTab === 'in_progress' ? 'text-blue-100' : 'text-gray-400' }}">
                In Progress
            </p>
        </button>

        {{-- Exceeded --}}
        <button wire:click="setTab('exceeded')"
                class="rounded-xl border px-5 py-4 text-left transition
                       {{ $activeTab === 'exceeded'
                          ? 'bg-red-600 border-red-600 shadow-sm'
                          : 'bg-white border-gray-200 hover:border-red-300' }}">
            <p class="text-2xl font-extrabold {{ $activeTab === 'exceeded' ? 'text-white' : 'text-red-600' }}">
                {{ $counts['exceeded'] }}
            </p>
            <p class="text-xs mt-0.5 font-medium {{ $activeTab === 'exceeded' ? 'text-red-100' : 'text-gray-400' }}">
                Exceeded Deadline
            </p>
            @if($counts['exceeded'] > 0 && $activeTab !== 'exceeded')
                <span class="inline-block mt-1 w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
            @endif
        </button>

        {{-- Done --}}
        <button wire:click="setTab('done')"
                class="rounded-xl border px-5 py-4 text-left transition
                       {{ $activeTab === 'done'
                          ? 'bg-green-600 border-green-600 shadow-sm'
                          : 'bg-white border-gray-200 hover:border-green-300' }}">
            <p class="text-2xl font-extrabold {{ $activeTab === 'done' ? 'text-white' : 'text-green-700' }}">
                {{ $counts['done'] }}
            </p>
            <p class="text-xs mt-0.5 font-medium {{ $activeTab === 'done' ? 'text-green-100' : 'text-gray-400' }}">
                Done
            </p>
        </button>

    </div>

    {{-- ── Filter + Sort bar ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-3">

        {{-- Project filter --}}
        @if($projects->isNotEmpty())
            <div class="flex items-center gap-2">
                <label class="text-xs font-medium text-gray-500">Project</label>
                <select wire:model.live="filterProject"
                        class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    <option value="0">All projects</option>
                    @foreach($projects as $proj)
                        <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        {{-- Sort controls --}}
        <div class="flex items-center gap-2 ml-auto">
            <span class="text-xs font-medium text-gray-400">Sort by</span>

            @foreach(['due_date' => 'Due Date', 'priority' => 'Priority', 'title' => 'Title'] as $field => $label)
                <button wire:click="setSort('{{ $field }}')"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium border transition
                               {{ $sortBy === $field
                                  ? 'bg-indigo-50 border-indigo-300 text-indigo-700'
                                  : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300' }}">
                    {{ $label }}
                    @if($sortBy === $field)
                        <svg class="w-3 h-3 {{ $sortDir === 'desc' ? 'rotate-180' : '' }} transition-transform"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                        </svg>
                    @endif
                </button>
            @endforeach
        </div>

    </div>

    {{-- ── Tab strip ────────────────────────────────────────────────────────── --}}
    <div class="flex border-b border-gray-200">
        @php
            $tabs = [
                'pending'     => ['label' => 'Pending',           'active' => 'border-gray-700 text-gray-800'],
                'in_progress' => ['label' => 'In Progress',       'active' => 'border-blue-600 text-blue-700'],
                'exceeded'    => ['label' => 'Exceeded Deadline', 'active' => 'border-red-600 text-red-700'],
                'done'        => ['label' => 'Done',              'active' => 'border-green-600 text-green-700'],
            ];
        @endphp

        @foreach($tabs as $key => $tab)
            <button wire:click="setTab('{{ $key }}')"
                    class="relative px-5 py-3 text-sm font-medium border-b-2 transition -mb-px
                           {{ $activeTab === $key
                              ? $tab['active']
                              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                {{ $tab['label'] }}
                @if($counts[$key] > 0)
                    <span class="ml-1.5 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-xs font-semibold
                                 {{ $activeTab === $key ? 'bg-current/10' : 'bg-gray-100 text-gray-500' }}">
                        {{ $counts[$key] }}
                    </span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- ── Task list ────────────────────────────────────────────────────────── --}}
    <div>
        @if($tasks->isEmpty())
            <div class="bg-white rounded-xl border border-gray-200 py-20 text-center">
                @php
                    $emptyIcon = match($activeTab) {
                        'exceeded' => '✅',
                        'done'     => '📋',
                        default    => '🎉',
                    };
                    $emptyMsg = match($activeTab) {
                        'pending'     => 'No pending tasks. You\'re all caught up!',
                        'in_progress' => 'Nothing in progress right now.',
                        'exceeded'    => 'No overdue tasks. Great work!',
                        'done'        => 'No completed tasks yet.',
                        default       => 'No tasks here.',
                    };
                @endphp
                <p class="text-3xl mb-3">{{ $emptyIcon }}</p>
                <p class="text-sm text-gray-400">{{ $emptyMsg }}</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($tasks as $task)
                    @php
                        $today       = now()->toDateString();
                        $isOverdue   = $task->due_date && $task->due_date->toDateString() < $today && $task->status !== 'done';
                        $isDueToday  = $task->due_date && $task->due_date->toDateString() === $today && $task->status !== 'done';
                        $daysLeft    = $task->due_date ? (int) now()->startOfDay()->diffInDays($task->due_date, false) : null;
                        $isExpanded  = $expandedTaskId === $task->id;

                        $priorityConfig = match($task->priority) {
                            'high'   => ['bar' => 'bg-red-500',    'text' => 'text-red-700',    'bg' => 'bg-red-50',    'badge' => 'bg-red-100 text-red-700',    'label' => 'High'],
                            'medium' => ['bar' => 'bg-yellow-400', 'text' => 'text-yellow-700', 'bg' => 'bg-yellow-50', 'badge' => 'bg-yellow-100 text-yellow-700','label' => 'Medium'],
                            default  => ['bar' => 'bg-gray-300',   'text' => 'text-gray-500',   'bg' => 'bg-gray-50',   'badge' => 'bg-gray-100 text-gray-500',   'label' => 'Low'],
                        };

                        $cardBorder = $isOverdue ? 'border-red-200' : 'border-gray-200';
                        $cardBg     = $isExpanded ? 'bg-indigo-50/30' : ($isOverdue ? 'bg-red-50/30' : 'bg-white');
                    @endphp

                    <div class="rounded-xl border {{ $cardBorder }} {{ $cardBg }} shadow-sm overflow-hidden transition-all duration-200">

                        {{-- ── Card header (always visible) ─────────────────── --}}
                        <div class="flex items-stretch">

                            {{-- Priority stripe --}}
                            <div class="w-1 flex-shrink-0 {{ $priorityConfig['bar'] }}"></div>

                            <div class="flex-1 px-5 py-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">

                                    {{-- Left: title + meta --}}
                                    <button wire:click="toggleExpand({{ $task->id }})"
                                            class="flex-1 min-w-0 text-left group">

                                        <div class="flex items-center gap-2 flex-wrap mb-1">
                                            {{-- Expand chevron --}}
                                            <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0 transition-transform {{ $isExpanded ? 'rotate-90' : '' }}"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>

                                            <h3 class="text-sm font-semibold text-gray-900 group-hover:text-indigo-700 transition
                                                       {{ $task->status === 'done' ? 'line-through text-gray-400' : '' }}">
                                                {{ $task->title }}
                                            </h3>

                                            {{-- Priority badge --}}
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $priorityConfig['badge'] }}">
                                                {{ $priorityConfig['label'] }}
                                            </span>

                                            {{-- Overdue / Due today badge --}}
                                            @if($isOverdue)
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                                    Overdue
                                                </span>
                                            @elseif($isDueToday)
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                                                    Due today
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Short description preview (collapsed only) --}}
                                        @if(!$isExpanded && $task->description)
                                            <p class="text-xs text-gray-400 mb-2 line-clamp-1 pl-5">{{ $task->description }}</p>
                                        @endif

                                        {{-- Project / team breadcrumb --}}
                                        <div class="flex items-center gap-1.5 text-xs text-gray-400 pl-5">
                                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M3 7h18M3 12h18M3 17h18"/>
                                            </svg>
                                            <span>{{ $task->project->name }}</span>
                                            <span class="text-gray-300">/</span>
                                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M17 20h5v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2h5M12 12a4 4 0 100-8 4 4 0 000 8z"/>
                                            </svg>
                                            <span>{{ $task->team->name }}</span>
                                        </div>
                                    </button>

                                    {{-- Right: due date + action buttons --}}
                                    <div class="flex flex-col items-end gap-2 flex-shrink-0">

                                        {{-- Due date --}}
                                        @if($task->due_date)
                                            <div class="text-right">
                                                <p class="text-xs font-semibold
                                                           {{ $isOverdue ? 'text-red-600' : ($isDueToday ? 'text-amber-600' : 'text-gray-600') }}">
                                                    {{ $task->due_date ? $task->due_date->format('M d, Y') : '—' }}
                                                </p>
                                                <p class="text-xs {{ $isOverdue ? 'text-red-400' : ($isDueToday ? 'text-amber-500' : 'text-gray-400') }}">
                                                    @if($isOverdue)
                                                        {{ abs($daysLeft) }}d overdue
                                                    @elseif($isDueToday)
                                                        Due today
                                                    @elseif($daysLeft !== null)
                                                        in {{ $daysLeft }}d
                                                    @endif
                                                </p>
                                            </div>
                                        @endif

                                        {{-- Quick-action buttons (compact, always visible) --}}
                                        <div class="flex items-center gap-1.5">
                                            @if($task->status !== 'in_progress')
                                                <button wire:click="setStatus({{ $task->id }}, 'in_progress')"
                                                        title="Mark In Progress"
                                                        class="px-2.5 py-1 text-xs font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition">
                                                    {{ $task->status === 'done' ? 'Reopen' : 'Start' }}
                                                </button>
                                            @endif

                                            @if($task->status !== 'done')
                                                <button wire:click="setStatus({{ $task->id }}, 'done')"
                                                        title="Mark Done"
                                                        class="px-2.5 py-1 text-xs font-medium text-green-600 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition">
                                                    Done
                                                </button>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-xs font-medium text-green-600">
                                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd"
                                                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                              clip-rule="evenodd"/>
                                                    </svg>
                                                    Completed
                                                </span>
                                            @endif
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ── Expanded detail panel ─────────────────────────── --}}
                        @if($isExpanded)
                            <div class="border-t border-gray-100 bg-white px-6 py-5">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                                    {{-- Description --}}
                                    <div class="md:col-span-2 space-y-4">
                                        <div>
                                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Description</h4>
                                            @if($task->description)
                                                <p class="text-sm text-gray-700 leading-relaxed">{{ $task->description }}</p>
                                            @else
                                                <p class="text-sm text-gray-400 italic">No description provided.</p>
                                            @endif
                                        </div>

                                        {{-- Full status actions --}}
                                        <div>
                                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Update Status</h4>
                                            <div class="flex flex-wrap gap-2">

                                                <button wire:click="setStatus({{ $task->id }}, 'pending')"
                                                        @class([
                                                            'px-3 py-1.5 text-xs font-medium rounded-lg border transition',
                                                            'bg-gray-700 border-gray-700 text-white cursor-default' => $task->status === 'pending',
                                                            'bg-white border-gray-200 text-gray-600 hover:bg-gray-50' => $task->status !== 'pending',
                                                        ])
                                                        @disabled($task->status === 'pending')>
                                                    Pending
                                                </button>

                                                <button wire:click="setStatus({{ $task->id }}, 'in_progress')"
                                                        @class([
                                                            'px-3 py-1.5 text-xs font-medium rounded-lg border transition',
                                                            'bg-blue-600 border-blue-600 text-white cursor-default' => $task->status === 'in_progress',
                                                            'bg-white border-blue-200 text-blue-600 hover:bg-blue-50' => $task->status !== 'in_progress',
                                                        ])
                                                        @disabled($task->status === 'in_progress')>
                                                    In Progress
                                                </button>

                                                <button wire:click="setStatus({{ $task->id }}, 'done')"
                                                        @class([
                                                            'px-3 py-1.5 text-xs font-medium rounded-lg border transition',
                                                            'bg-green-600 border-green-600 text-white cursor-default' => $task->status === 'done',
                                                            'bg-white border-green-200 text-green-600 hover:bg-green-50' => $task->status !== 'done',
                                                        ])
                                                        @disabled($task->status === 'done')>
                                                    Done
                                                </button>

                                            </div>
                                        </div>
                                    </div>

                                    {{-- Task meta sidebar --}}
                                    <div class="space-y-3">
                                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Details</h4>

                                        <dl class="space-y-2 text-sm">
                                            <div class="flex justify-between gap-2">
                                                <dt class="text-gray-400">Project</dt>
                                                <dd class="font-medium text-gray-800 text-right">{{ $task->project->name }}</dd>
                                            </div>
                                            <div class="flex justify-between gap-2">
                                                <dt class="text-gray-400">Team</dt>
                                                <dd class="font-medium text-gray-800 text-right">{{ $task->team->name }}</dd>
                                            </div>
                                            <div class="flex justify-between gap-2">
                                                <dt class="text-gray-400">Priority</dt>
                                                <dd>
                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $priorityConfig['badge'] }}">
                                                        {{ $priorityConfig['label'] }}
                                                    </span>
                                                </dd>
                                            </div>
                                            @if($task->start_date)
                                                <div class="flex justify-between gap-2">
                                                    <dt class="text-gray-400">Start Date</dt>
                                                    <dd class="font-medium text-gray-800">{{ $task->start_date->format('M d, Y') }}</dd>
                                                </div>
                                            @endif
                                            <div class="flex justify-between gap-2">
                                                <dt class="text-gray-400">Due Date</dt>
                                                <dd class="font-medium {{ $isOverdue ? 'text-red-600' : 'text-gray-800' }}">
                                                    {{ $task->due_date ? $task->due_date->format('M d, Y') : '—' }}
                                                </dd>
                                            </div>
                                            <div class="flex justify-between gap-2">
                                                <dt class="text-gray-400">Status</dt>
                                                <dd>
                                                    @php
                                                        $statusBadge = match($task->status) {
                                                            'pending'     => 'bg-gray-100 text-gray-600',
                                                            'in_progress' => 'bg-blue-100 text-blue-700',
                                                            'done'        => 'bg-green-100 text-green-700',
                                                            default       => 'bg-gray-100 text-gray-500',
                                                        };
                                                        $statusLabel = match($task->status) {
                                                            'pending'     => 'Pending',
                                                            'in_progress' => 'In Progress',
                                                            'done'        => 'Done',
                                                            default       => ucfirst($task->status),
                                                        };
                                                    @endphp
                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge }}">
                                                        {{ $statusLabel }}
                                                    </span>
                                                </dd>
                                            </div>
                                        </dl>

                                        @if($isOverdue)
                                            <div class="mt-3 rounded-lg bg-red-50 border border-red-100 px-3 py-2 text-xs text-red-700">
                                                This task is <strong>{{ abs($daysLeft) }} day{{ abs($daysLeft) !== 1 ? 's' : '' }} overdue</strong>.
                                                Update the status or contact your team lead.
                                            </div>
                                        @endif
                                    </div>

                                </div>
                            </div>
                        @endif

                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
