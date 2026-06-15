<div>
    {{-- Flash message --}}
    @if(session('success'))
        <x-floating-notification :message="session('success')" />
    @endif

    {{-- Header row --}}
    <div class="flex items-center justify-between mb-6">
        <div></div>
        @if(!$showForm)
            <button wire:click="openCreate"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Team
            </button>
        @endif
    </div>

    {{-- Create / Edit form --}}
    @if($showForm)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-800 mb-5">
                {{ $editingId ? 'Edit Team' : 'New Team' }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Team Name <span class="text-red-500">*</span></label>
                    <input wire:model="name" type="text" placeholder="e.g. Frontend Team"
                           class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
                    @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Project --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project <span class="text-red-500">*</span></label>
                    <select wire:model.live="projectId"
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('projectId') border-red-400 @enderror">
                        <option value="">— Select project —</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                    @error('projectId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Team Lead --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Team Lead <span class="text-red-500">*</span></label>
                    <select wire:model="leadId"
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('leadId') border-red-400 @enderror">
                        <option value="">— Select team lead —</option>
                        @foreach($leads as $lead)
                            <option value="{{ $lead->id }}">{{ $lead->name }}</option>
                        @endforeach
                    </select>
                    @error('leadId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Members --}}
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Team Members</label>
                    <div @class([
                        'rounded-lg border bg-white px-3 py-2',
                        'border-red-400' => $errors->has('memberIds'),
                        'border-gray-300' => ! $errors->has('memberIds'),
                    ])>
                        @if($members->isEmpty())
                            <p class="py-4 text-center text-sm text-gray-400">No team leads or members found.</p>
                        @else
                            <div class="grid max-h-44 grid-cols-1 gap-1 overflow-y-auto pr-1 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($members as $member)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 text-sm transition hover:bg-indigo-50">
                                        <input type="checkbox"
                                               wire:model="memberIds"
                                               value="{{ $member->id }}"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                                            {{ strtoupper(substr($member->name, 0, 1)) }}
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium text-gray-700">{{ $member->name }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-gray-400">Select the people who belong to this team.</p>
                    @error('memberIds') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    @error('memberIds.*') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Teams in selected project --}}
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select teams for this project</label>
                    <div @class([
                        'rounded-lg border bg-white px-3 py-2',
                        'border-red-400' => $errors->has('projectTeamIds'),
                        'border-gray-300' => ! $errors->has('projectTeamIds'),
                        'opacity-60' => ! $projectId,
                    ])>
                        @if(! $projectId)
                            <p class="py-4 text-center text-sm text-gray-400">Select a project first.</p>
                        @else
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <input type="text"
                                       wire:model.live.debounce.300ms="teamSearch"
                                       placeholder="Search teams..."
                                       class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <button type="button"
                                        wire:click="selectAllProjectTeams"
                                        class="rounded-lg border border-indigo-200 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">
                                    Select all
                                </button>
                                <button type="button"
                                        wire:click="clearProjectTeams"
                                        class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                                    Clear
                                </button>
                            </div>

                            @if($projectTeamOptions->isEmpty())
                                <p class="py-4 text-center text-sm text-gray-400">No teams found.</p>
                            @else
                                <div class="max-h-44 space-y-1 overflow-y-auto pr-1">
                                    @foreach($projectTeamOptions as $teamOption)
                                        <label class="flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 text-sm transition hover:bg-indigo-50">
                                            <input type="checkbox"
                                                   wire:model="projectTeamIds"
                                                   value="{{ $teamOption->id }}"
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                                                {{ strtoupper(substr($teamOption->name, 0, 1)) }}
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block font-medium text-gray-700 truncate">{{ $teamOption->name }}</span>
                                                <span class="block text-xs text-gray-400 truncate">
                                                    {{ $teamOption->project?->name ?? 'No project' }} / Lead: {{ $teamOption->lead?->name ?? 'Unassigned' }}
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>

                    @if(!empty($projectTeamIds))
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach($selectedProjectTeams as $teamOption)
                                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                                    {{ $teamOption->name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                    <p class="mt-1 text-xs text-gray-400">Selected teams will be assigned to the chosen project when you save.</p>
                    @error('projectTeamIds') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    @error('projectTeamIds.*') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

            </div>

            <div class="flex items-center gap-3 mt-6">
                <button wire:click="save"
                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    {{ $editingId ? 'Update Team' : 'Create Team' }}
                </button>
                <button wire:click="cancelForm"
                        class="px-5 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
            </div>
        </div>
    @endif

    {{-- Teams list --}}
    <div class="space-y-3" @if(!$showForm) wire:poll.visible.15s @endif>
        @forelse($teams as $team)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                {{-- Team row --}}
                <div class="flex items-center gap-4 px-6 py-4">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-900">{{ $team->name }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $team->project->name }}</p>
                    </div>
                    <div class="text-sm text-gray-600 hidden md:block">
                        Lead: <span class="font-medium text-gray-800">{{ $team->lead->name }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        @foreach($team->members->take(4) as $member)
                            <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-semibold"
                                 title="{{ $member->name }}">
                                {{ strtoupper(substr($member->name, 0, 1)) }}
                            </div>
                        @endforeach
                        @if($team->members->count() > 4)
                            <div class="w-7 h-7 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center text-xs font-semibold">
                                +{{ $team->members->count() - 4 }}
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 text-xs font-medium">
                        <button wire:click="showDetails({{ $team->id }})"
                                class="text-indigo-600 hover:text-indigo-800 px-3 py-1.5 rounded-lg border border-indigo-200 transition">
                            Details
                        </button>
                        <button wire:click="openEdit({{ $team->id }})"
                                class="text-gray-600 hover:text-gray-800 px-3 py-1.5 rounded-lg border border-gray-200 transition">
                            Edit
                        </button>
                        <button wire:click="confirmDelete({{ $team->id }})"
                                class="text-red-500 hover:text-red-700 px-3 py-1.5 rounded-lg border border-red-200 transition">
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            @if($detailsTeamId === $team->id)
                @php
                    $tasks = $detailsTeam->limitedTasks ?? collect();
                    $totalTasks = $tasks->count();
                    $doneTasks = $tasks->where('status', 'done')->count();
                    $inProgressTasks = $tasks->where('status', 'in_progress')->count();
                    $reviewTasks = $tasks->where('status', 'review')->count();
                    $pendingTasks = $tasks->where('status', 'pending')->count();
                    $overdueTasks = $tasks->filter(fn ($task) => $task->due_date && $task->isExceededDeadline())->count();
                    $completion = $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0;
                    $statusClass = fn ($status) => match($status) {
                        'done' => 'bg-green-100 text-green-700',
                        'in_progress' => 'bg-blue-100 text-blue-700',
                        'review' => 'bg-amber-100 text-amber-800',
                        'pending' => 'bg-gray-100 text-gray-600',
                        default => 'bg-gray-100 text-gray-600',
                    };
                @endphp

                <div class="bg-white rounded-b-xl border border-t-0 border-gray-100 p-5">
                    <div class="space-y-4">
                        <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $detailsTeam->name }}</h3>
                                    <p class="mt-1 text-sm text-gray-500">{{ $detailsTeam->project?->name ?? 'No project assigned' }}</p>
                                </div>
                                <div class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-indigo-700 shadow-sm">
                                    {{ $completion }}% complete
                                </div>
                            </div>
                            <div class="mt-4 h-2 overflow-hidden rounded-full bg-white">
                                <div class="h-2 rounded-full bg-indigo-600" style="width: {{ $completion }}%"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-xs font-medium text-gray-400">Team lead</p>
                                <p class="mt-1 truncate text-sm font-semibold text-gray-800">{{ $detailsTeam->lead?->name ?? 'Unassigned' }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-xs font-medium text-gray-400">Members</p>
                                <p class="mt-1 text-sm font-semibold text-gray-800">{{ $detailsTeam->members->count() }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-xs font-medium text-gray-400">Tasks</p>
                                <p class="mt-1 text-sm font-semibold text-gray-800">{{ $totalTasks }}</p>
                            </div>
                            <div class="rounded-lg bg-red-50 p-3">
                                <p class="text-xs font-medium text-red-400">Overdue</p>
                                <p class="mt-1 text-sm font-semibold text-red-700">{{ $overdueTasks }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                            <div class="rounded-lg bg-green-50 p-3 text-center">
                                <p class="text-lg font-bold text-green-700">{{ $doneTasks }}</p>
                                <p class="text-xs font-medium text-green-700">Done</p>
                            </div>
                            <div class="rounded-lg bg-blue-50 p-3 text-center">
                                <p class="text-lg font-bold text-blue-700">{{ $inProgressTasks }}</p>
                                <p class="text-xs font-medium text-blue-700">In progress</p>
                            </div>
                            <div class="rounded-lg bg-amber-50 p-3 text-center">
                                <p class="text-lg font-bold text-amber-800">{{ $reviewTasks }}</p>
                                <p class="text-xs font-medium text-amber-800">Review</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3 text-center">
                                <p class="text-lg font-bold text-gray-700">{{ $pendingTasks }}</p>
                                <p class="text-xs font-medium text-gray-500">Pending</p>
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-sm font-semibold text-gray-800">Members</p>
                            @if($detailsTeam->members->isEmpty())
                                <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-400">No members assigned yet.</p>
                            @else
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    @foreach($detailsTeam->members as $member)
                                        <div class="flex items-center gap-3 rounded-lg border border-gray-100 bg-white p-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                                                {{ strtoupper(substr($member->name, 0, 1)) }}
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-semibold text-gray-800">{{ $member->name }}</p>
                                                <p class="truncate text-xs text-gray-400">{{ $member->email }}</p>
                                            </div>
                                            <span @class([
                                                'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                                'bg-indigo-100 text-indigo-700' => $member->pivot?->role === 'lead',
                                                'bg-gray-100 text-gray-600' => $member->pivot?->role !== 'lead',
                                            ])>
                                                {{ $member->pivot?->role === 'lead' ? 'Lead' : 'Member' }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div>
                            <p class="mb-2 text-sm font-semibold text-gray-800">Tasks</p>
                            @if($tasks->isEmpty())
                                <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-400">No tasks assigned to this team yet.</p>
                            @else
                                <div class="max-h-64 space-y-2 overflow-y-auto pr-1">
                                    @foreach($tasks->sortByDesc('due_date')->take(8) as $task)
                                        @php
                                            $assignees = $task->getAllAssignees();
                                        @endphp
                                        <div class="rounded-lg border border-gray-100 bg-white p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-semibold text-gray-800">{{ $task->title }}</p>
                                                    <p class="mt-1 text-xs text-gray-400">
                                                        Start {{ $task->start_date?->format('M d, Y') ?? 'Not set' }}
                                                        <span class="mx-1">/</span>
                                                        Due {{ $task->due_date?->format('M d, Y') ?? 'Not set' }}
                                                    </p>
                                                </div>
                                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusClass($task->status) }}">
                                                    {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                                </span>
                                            </div>

                                            @if($assignees->isNotEmpty())
                                                <div class="mt-2 flex flex-wrap gap-1.5">
                                                    @foreach($assignees as $assignee)
                                                        <span class="rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-medium text-sky-700">
                                                            {{ $assignee->name }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="pt-2">
                            <button wire:click="showDetails({{ $team->id }})" class="px-3 py-1 text-sm text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Close</button>
                        </div>
                    </div>
                </div>
            @endif
        @empty
            <div class="bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-400">
                <p class="text-sm">No teams yet. Create one to get started.</p>
            </div>
        @endforelse
    </div>

    

    <x-confirmation-modal wire:model="confirmingDelete" maxWidth="md">
        <x-slot name="title">
            Delete team?
        </x-slot>

        <x-slot name="content">
            This will remove the team and all of its tasks.
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="cancelDelete" wire:loading.attr="disabled">
                Cancel
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="deleteConfirmed" wire:loading.attr="disabled">
                Delete
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>
</div>
