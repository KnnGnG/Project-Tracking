<div>
    {{-- Flash message --}}
    @if(session('success'))
        <x-floating-notification :message="session('success')" />
    @endif

    {{-- Header row --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <input wire:model.live.debounce.300ms="search"
                   type="text"
                   placeholder="Search projects…"
                   class="w-64 px-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        @if(!$showForm)
            <button wire:click="openCreate"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Project
            </button>
        @endif
    </div>

    {{-- Create / Edit form --}}
    @if($showForm)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-800 mb-5">
                {{ $editingId ? 'Edit Project' : 'New Project' }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Name --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project Name <span class="text-red-500">*</span></label>
                    <input wire:model="name" type="text" placeholder="e.g. Website Redesign"
                           class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
                    @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Description --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea wire:model="description" rows="3" placeholder="Brief description of the project…"
                              class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                </div>

                {{-- Start Date --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date <span class="text-red-500">*</span></label>
                    <input wire:model="startDate" type="date"
                           class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('startDate') border-red-400 @enderror">
                    @error('startDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- End Date --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date <span class="text-red-500">*</span></label>
                    <input wire:model="endDate" type="date"
                           class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('endDate') border-red-400 @enderror">
                    @error('endDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Status --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select wire:model="status"
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="active">Active</option>
                        <option value="on_hold">On Hold</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                {{-- Client --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client (optional)</label>
                    <select wire:model="clientId"
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">— No client —</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>

            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-3 mt-6">
                <button wire:click="save"
                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    {{ $editingId ? 'Update Project' : 'Create Project' }}
                </button>
                <button wire:click="cancelForm"
                        class="px-5 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
            </div>
        </div>
    @endif

    {{-- Projects table --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" @if(!$showForm) wire:poll.visible.15s @endif>
        @if($projects->isEmpty())
            <div class="py-16 text-center text-gray-400">
                <svg class="w-10 h-10 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M3 7h18M3 12h18M3 17h18"/>
                </svg>
                <p class="text-sm">No projects found.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-600">Project</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-600">Client</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-600">Timeline</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-600">Status</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-600">Progress</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-600">Teams</th>
                        <th class="w-56 px-4 py-3 text-left font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($projects as $project)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <p class="font-medium text-gray-900">{{ $project->name }}</p>
                                @if($project->description)
                                    <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ $project->description }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                {{ $project->client?->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-gray-500 whitespace-nowrap">
                                {{ $project->start_date->format('M d, Y') }} →
                                {{ $project->end_date->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $badge = match($project->status) {
                                        'active'    => 'bg-green-100 text-green-700',
                                        'on_hold'   => 'bg-yellow-100 text-yellow-700',
                                        'completed' => 'bg-blue-100 text-blue-700',
                                        default     => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                    {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @php $pct = $project->completionPercentage() @endphp
                                <button type="button"
                                        wire:click="toggleProgressDetails({{ $project->id }})"
                                        class="flex items-center gap-2 rounded-lg px-2 py-1 -mx-2 text-left transition hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        aria-expanded="{{ $progressProjectId === $project->id ? 'true' : 'false' }}">
                                    <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-2 bg-indigo-500 rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ $pct }}%</span>
                                </button>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                {{ $project->teams->count() }}
                            </td>
                            <td class="w-56 px-4 py-4 whitespace-nowrap">
                                <div class="flex items-center justify-start gap-4">
                                    <button wire:click="showDetails({{ $project->id }})"
                                            class="inline-flex w-12 justify-center text-indigo-600 hover:text-indigo-800 text-xs font-medium transition">
                                        Details
                                    </button>
                                    <button wire:click="openEdit({{ $project->id }})"
                                            class="inline-flex w-9 justify-center text-indigo-600 hover:text-indigo-800 text-xs font-medium transition">
                                        Edit
                                    </button>
                                    <button wire:click="confirmDelete({{ $project->id }})"
                                            class="inline-flex w-11 justify-center text-red-500 hover:text-red-700 text-xs font-medium transition">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>

                        @if($detailsProjectId === $project->id)
                            @php
                                        $tasks = $detailsProjectTasks ?? collect();
                                $teams = $detailsProject?->teams ?? collect();
                                $totalTasks = $tasks->count();
                                $doneTasks = $tasks->where('status', 'done')->count();
                                $inProgressTasks = $tasks->where('status', 'in_progress')->count();
                                $reviewTasks = $tasks->where('status', 'review')->count();
                                $pendingTasks = $tasks->where('status', 'pending')->count();
                                $overdueTasks = $tasks->filter(fn ($task) => $task->due_date && $task->isExceededDeadline())->count();
                                $completion = $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0;
                                $statusClass = fn ($status) => match($status) {
                                    'active' => 'bg-green-100 text-green-700',
                                    'on_hold' => 'bg-yellow-100 text-yellow-700',
                                    'completed' => 'bg-blue-100 text-blue-700',
                                    'done' => 'bg-green-100 text-green-700',
                                    'in_progress' => 'bg-blue-100 text-blue-700',
                                    'review' => 'bg-amber-100 text-amber-800',
                                    'pending' => 'bg-gray-100 text-gray-600',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <tr>
                                <td colspan="7" class="px-6 py-5 bg-white">
                                    <div class="space-y-5">
                                        <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
                                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <h3 class="text-lg font-semibold text-gray-900">{{ $detailsProject->name }}</h3>
                                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass($detailsProject->status) }}">
                                                            {{ ucfirst(str_replace('_', ' ', $detailsProject->status)) }}
                                                        </span>
                                                    </div>
                                                    <p class="mt-1 text-sm text-gray-500">
                                                        {{ $detailsProject->description ?: 'No description provided.' }}
                                                    </p>
                                                </div>
                                                <div class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-indigo-700 shadow-sm">
                                                    {{ $completion }}% complete
                                                </div>
                                            </div>
                                            <div class="mt-4 h-2 overflow-hidden rounded-full bg-white">
                                                <div class="h-2 rounded-full bg-indigo-600" style="width: {{ $completion }}%"></div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div class="rounded-lg bg-gray-50 p-3">
                                                <p class="text-xs font-medium text-gray-400">Client</p>
                                                <p class="mt-1 truncate text-sm font-semibold text-gray-800">{{ $detailsProject->client?->name ?? 'No client' }}</p>
                                            </div>
                                            <div class="rounded-lg bg-gray-50 p-3">
                                                <p class="text-xs font-medium text-gray-400">Timeline</p>
                                                <p class="mt-1 text-sm font-semibold text-gray-800">
                                                    {{ $detailsProject->start_date->format('M d, Y') }} - {{ $detailsProject->end_date->format('M d, Y') }}
                                                </p>
                                            </div>
                                            <div class="rounded-lg bg-red-50 p-3">
                                                <p class="text-xs font-medium text-red-400">Overdue tasks</p>
                                                <p class="mt-1 text-sm font-semibold text-red-700">{{ $overdueTasks }}</p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-3 md:grid-cols-6">
                                            <div class="rounded-lg bg-gray-50 p-3 text-center">
                                                <p class="text-lg font-bold text-gray-800">{{ $totalTasks }}</p>
                                                <p class="text-xs font-medium text-gray-500">Tasks</p>
                                            </div>
                                            <div class="rounded-lg bg-indigo-50 p-3 text-center">
                                                <p class="text-lg font-bold text-indigo-700">{{ $teams->count() }}</p>
                                                <p class="text-xs font-medium text-indigo-700">Teams</p>
                                            </div>
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
                                            <p class="mb-2 text-sm font-semibold text-gray-800">Teams</p>
                                            @if($teams->isEmpty())
                                                <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-400">No teams assigned yet.</p>
                                            @else
                                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                    @foreach($teams as $team)
                                                        <div class="rounded-lg border border-gray-100 bg-white p-3">
                                                            <div class="flex items-start justify-between gap-3">
                                                                <div class="min-w-0">
                                                                    <p class="truncate text-sm font-semibold text-gray-800">{{ $team->name }}</p>
                                                                    <p class="mt-1 truncate text-xs text-gray-400">Lead: {{ $team->lead?->name ?? 'Unassigned' }}</p>
                                                                </div>
                                                                <span class="shrink-0 rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">
                                                                    {{ $team->members->count() }} members
                                                                </span>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>

                                        <div>
                                            <p class="mb-2 text-sm font-semibold text-gray-800">Tasks</p>
                                            @if($tasks->isEmpty())
                                                <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-400">No tasks created for this project yet.</p>
                                            @else
                                                <div class="max-h-64 space-y-2 overflow-y-auto pr-1">
                                                    @foreach($tasks->sortByDesc('due_date')->take(10) as $task)
                                                        @php
                                                            $assignees = $task->getAllAssignees();
                                                        @endphp
                                                        <div class="rounded-lg border border-gray-100 bg-white p-3">
                                                            <div class="flex items-start justify-between gap-3">
                                                                <div class="min-w-0">
                                                                    <p class="truncate text-sm font-semibold text-gray-800">{{ $task->title }}</p>
                                                                    <p class="mt-1 text-xs text-gray-400">
                                                                        {{ $task->team?->name ?? 'No team' }}
                                                                        <span class="mx-1">/</span>
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

                                        <div class="pt-3">
                                            <button wire:click="showDetails({{ $project->id }})" class="px-3 py-1 text-sm text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Close</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif

                        @if($progressProjectId === $project->id)
                                @php
                                    $projectTasks = $project->tasks;
                                    $memberTaskMap = collect();

                                    $projectTasks->each(function ($task) use ($memberTaskMap) {
                                        $assignees = $task->getAllAssignees();

                                        $assignees->each(function ($member) use ($memberTaskMap, $task) {
                                            $memberTaskMap->put(
                                                $member->id,
                                                [
                                                    'member' => $member,
                                                    'tasks' => $memberTaskMap->get($member->id, ['tasks' => collect()])['tasks']->push($task),
                                                ]
                                            );
                                        });
                                    });
                                @endphp
                            <tr>
                                <td colspan="7" class="px-6 py-5 bg-indigo-50/40">
                                    <div class="rounded-lg border border-indigo-100 bg-white p-4">
                                        <h3 class="text-sm font-semibold text-gray-800 mb-3">Member tasks</h3>
                                        @if($memberTaskMap->isEmpty())
                                            <p class="text-xs text-gray-400">No assigned tasks yet.</p>
                                        @else
                                            <div class="space-y-4">
                                                @foreach($memberTaskMap as $row)
                                                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                                        <p class="text-sm font-semibold text-gray-800 mb-2">{{ $row['member']->name }}</p>
                                                        <div class="overflow-x-auto">
                                                            <table class="w-full text-xs">
                                                                <thead>
                                                                    <tr class="text-left text-gray-400">
                                                                        <th class="py-2 font-semibold">Task</th>
                                                                        <th class="py-2 font-semibold">Team</th>
                                                                        <th class="py-2 font-semibold">Status</th>
                                                                        <th class="py-2 font-semibold">Start</th>
                                                                        <th class="py-2 font-semibold">Due</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-gray-200">
                                                                    @foreach($row['tasks'] as $task)
                                                                        @php
                                                                            $statusLabel = match($task->status) {
                                                                                'pending' => 'Pending',
                                                                                'in_progress' => 'In Progress',
                                                                                'review' => 'Review',
                                                                                'done' => 'Done',
                                                                                default => ucfirst(str_replace('_', ' ', $task->status)),
                                                                            };
                                                                            $statusClass = match($task->status) {
                                                                                'pending' => 'bg-gray-100 text-gray-600',
                                                                                'in_progress' => 'bg-blue-100 text-blue-700',
                                                                                'review' => 'bg-amber-100 text-amber-800',
                                                                                'done' => 'bg-green-100 text-green-700',
                                                                                default => 'bg-gray-100 text-gray-600',
                                                                            };
                                                                        @endphp
                                                                        <tr>
                                                                            <td class="py-2 pr-3 font-medium text-gray-700">{{ $task->title }}</td>
                                                                            <td class="py-2 pr-3 text-gray-500 whitespace-nowrap">{{ $task->team?->name ?? 'Unassigned' }}</td>
                                                                            <td class="py-2 pr-3 whitespace-nowrap">
                                                                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $statusClass }}">
                                                                                    {{ $statusLabel }}
                                                                                </span>
                                                                            </td>
                                                                            <td class="py-2 pr-3 text-gray-500 whitespace-nowrap">{{ $task->start_date?->format('M d, Y') ?? 'Not set' }}</td>
                                                                            <td class="py-2 text-gray-500 whitespace-nowrap">{{ $task->due_date?->format('M d, Y') ?? 'Not set' }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    

    <x-confirmation-modal wire:model="confirmingDelete" maxWidth="md">
        <x-slot name="title">
            Delete project?
        </x-slot>

        <x-slot name="content">
            This will remove the project, all of its teams, and all related tasks.
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
