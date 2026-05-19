<div class="space-y-6" wire:poll.visible.15s>

    {{-- Flash --}}
    @if(session('success'))
        <x-floating-notification :message="session('success')" />
    @endif

    {{-- ── Site-wide stat cards ─────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="{{ route('admin.users') }}"
           class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-5 hover:border-indigo-300 transition group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-3xl font-extrabold text-gray-900">{{ $stats['users'] }}</p>
                    <p class="text-sm text-gray-400 mt-0.5">Total Users</p>
                </div>
                <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center group-hover:bg-indigo-100 transition">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2h5M12 12a4 4 0 100-8 4 4 0 000 8z"/>
                    </svg>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.projects') }}"
           class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-5 hover:border-indigo-300 transition group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-3xl font-extrabold text-gray-900">{{ $stats['projects'] }}</p>
                    <p class="text-sm text-gray-400 mt-0.5">Projects</p>
                </div>
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center group-hover:bg-blue-100 transition">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/>
                    </svg>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.teams') }}"
           class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-5 hover:border-indigo-300 transition group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-3xl font-extrabold text-gray-900">{{ $stats['teams'] }}</p>
                    <p class="text-sm text-gray-400 mt-0.5">Teams</p>
                </div>
                <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center group-hover:bg-emerald-100 transition">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/>
                    </svg>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.tasks') }}"
           class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-5 hover:border-indigo-300 transition group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-3xl font-extrabold text-gray-900">{{ $stats['tasks'] }}</p>
                    <p class="text-sm text-gray-400 mt-0.5">Total Tasks</p>
                </div>
                <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center group-hover:bg-amber-100 transition">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                    </svg>
                </div>
            </div>
        </a>
    </div>

    {{-- ── Task overview bar ────────────────────────────────────────────────── --}}
    @if($stats['tasks'] > 0)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700">Tasks Overview</h3>
                <div class="flex items-center gap-4 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>Done ({{ $taskStats['done'] }})</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>In Progress ({{ $taskStats['in_progress'] }})</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-gray-200 border border-gray-300"></span>Pending ({{ $taskStats['pending'] }})</span>
                    @if($taskStats['overdue'] > 0)
                        <span class="flex items-center gap-1.5 text-red-500 font-medium">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>Overdue ({{ $taskStats['overdue'] }})
                        </span>
                    @endif
                </div>
            </div>
            <div class="flex h-3 rounded-full overflow-hidden bg-gray-100 gap-px">
                @if($taskStats['done'] > 0)
                    <div class="bg-green-500" style="width: {{ round(($taskStats['done'] / $stats['tasks']) * 100) }}%"></div>
                @endif
                @if($taskStats['in_progress'] > 0)
                    <div class="bg-blue-400" style="width: {{ round(($taskStats['in_progress'] / $stats['tasks']) * 100) }}%"></div>
                @endif
                @if($taskStats['pending'] > 0)
                    <div class="bg-gray-200" style="width: {{ round(($taskStats['pending'] / $stats['tasks']) * 100) }}%"></div>
                @endif
            </div>
        </div>
    @endif

    {{-- ── Main two-column row ──────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- ── New registrations (role approval) ──────────────────────────── --}}
        <div class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Recent Registrations</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Review and assign the correct role for each user.</p>
                </div>
                <a href="{{ route('admin.users') }}"
                   class="text-xs text-indigo-600 hover:text-indigo-800 font-medium transition">
                    Manage all users →
                </a>
            </div>

            @if($newUsers->isEmpty())
                <div class="py-12 text-center text-gray-400 text-sm">No users registered yet.</div>
            @else
                <ul class="divide-y divide-gray-50">
                    @foreach($newUsers as $user)
                        @php
                            $roleBadge = match($user->role) {
                                'admin'     => 'bg-purple-100 text-purple-700',
                                'client'    => 'bg-blue-100 text-blue-700',
                                'team_lead' => 'bg-indigo-100 text-indigo-700',
                                'member'    => 'bg-gray-100 text-gray-600',
                                default     => 'bg-gray-100 text-gray-500',
                            };
                        @endphp
                        <li class="px-6 py-4">
                            <div class="flex flex-wrap items-center gap-4 justify-between">
                                {{-- User info --}}
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <div class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-bold flex-shrink-0">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 truncate">{{ $user->name }}</p>
                                        <p class="text-xs text-gray-400 truncate">{{ $user->email }}</p>
                                    </div>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $roleBadge }}">
                                        {{ $user->roleName() }}
                                    </span>
                                </div>

                                {{-- Quick role assignment buttons --}}
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    @if($user->role !== 'team_lead')
                                        <button wire:click="approveRole({{ $user->id }}, 'team_lead')"
                                                class="px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition">
                                            Make Team Lead
                                        </button>
                                    @endif
                                    @if($user->role !== 'member')
                                        <button wire:click="approveRole({{ $user->id }}, 'member')"
                                                class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition">
                                            Set as Member
                                        </button>
                                    @endif
                                    @if($user->role !== 'client')
                                        <button wire:click="approveRole({{ $user->id }}, 'client')"
                                                class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition">
                                            Set as Client
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- ── Active projects sidebar ──────────────────────────────────────── --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Active Projects</h3>
                <a href="{{ route('admin.projects') }}"
                   class="text-xs text-indigo-600 hover:text-indigo-800 font-medium transition">View all →</a>
            </div>

            @if($activeProjects->isEmpty())
                <div class="py-10 text-center text-gray-400 text-xs">No active projects.</div>
            @else
                <ul class="divide-y divide-gray-50">
                    @foreach($activeProjects as $project)
                        @php $pct = $project->completionPercentage(); @endphp
                        <li class="px-5 py-4">
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <p class="text-sm font-medium text-gray-800 truncate flex-1">{{ $project->name }}</p>
                                <span class="text-xs font-bold text-indigo-600 flex-shrink-0">{{ $pct }}%</span>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden mb-1">
                                <div class="h-1.5 bg-indigo-500 rounded-full transition-all duration-500"
                                     style="width: {{ $pct }}%"></div>
                            </div>
                            <div class="flex items-center justify-between text-xs text-gray-400 mt-1">
                                <span>{{ $project->teams->count() }} team{{ $project->teams->count() !== 1 ? 's' : '' }}</span>
                                <span>Due {{ $project->end_date->format('M d') }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

    </div>
</div>
