<div class="space-y-6">

    {{-- Flash --}}
    @if(session('success'))
        <x-floating-notification :message="session('success')" />
    @endif

    {{-- ── Filters + New Task button ────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-3 justify-between">
        <div class="flex items-center gap-3">
            {{-- Team filter --}}
            <select wire:model.live="filterTeamId"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All My Teams</option>
                @foreach($leadTeams as $team)
                    <option value="{{ $team->id }}">{{ $team->name }} — {{ $team->project->name }}</option>
                @endforeach
            </select>

            {{-- Status filter --}}
            <select wire:model.live="filterStatus"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="in_progress">In Progress</option>
                <option value="review">Review</option>
                <option value="done">Done</option>
            </select>
        </div>

        @if(!$showForm)
            <button wire:click="openCreate"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Assign Task
            </button>
        @endif
    </div>

    {{-- ── Task form ─────────────────────────────────────────────────────────── --}}
    @if($showForm)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-5">
                {{ $editingId ? 'Edit Task' : 'Assign New Task' }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Title --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Task Title <span class="text-red-500">*</span>
                    </label>
                    <input wire:model="title" type="text" placeholder="e.g. Build login page"
                           class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('title') border-red-400 @enderror">
                    @error('title') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Description --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea wire:model="description" rows="2" placeholder="Optional details…"
                              class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                </div>

                {{-- Team --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Team <span class="text-red-500">*</span>
                    </label>
                    <select wire:model.live="teamId"
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('teamId') border-red-400 @enderror">
                        <option value="">— Select team —</option>
                        @foreach($leadTeams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                    @error('teamId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Assign To --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Assign To <span class="text-red-500">*</span>
                    </label>
                    <div @class([
                        'rounded-lg border bg-white px-3 py-2',
                        'border-red-400' => $errors->has('assignedTo'),
                        'border-gray-300' => ! $errors->has('assignedTo'),
                        'opacity-60' => ! $teamId,
                    ])>
                        @if(!$teamId)
                            <p class="py-4 text-center text-sm text-gray-400">Select a team first.</p>
                        @elseif($membersForForm->isEmpty())
                            <p class="py-4 text-center text-sm text-gray-400">No members in this team yet.</p>
                        @else
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <input type="text"
                                       wire:model.live.debounce.300ms="memberSearch"
                                       placeholder="Search members..."
                                       class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <button type="button" wire:click="selectAllMembers" class="rounded-lg border border-indigo-200 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">
                                    Select all
                                </button>
                                <button type="button" wire:click="clearMembers" class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                                    Clear
                                </button>
                            </div>
                            <div class="max-h-40 space-y-1 overflow-y-auto pr-1">
                                @foreach($membersForForm as $member)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 text-sm transition hover:bg-indigo-50">
                                        <input type="checkbox"
                                               wire:model="assignedTo"
                                               value="{{ $member->id }}"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                                            {{ strtoupper(substr($member->name, 0, 1)) }}
                                        </span>
                                        <span class="font-medium text-gray-700">{{ $member->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @if(!empty($assignedTo))
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach($membersForForm->whereIn('id', array_map('intval', $assignedTo)) as $member)
                                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                                    {{ $member->name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                    <select wire:model="assignedTo"
                            multiple
                            size="5"
                            class="hidden"
                            @disabled(!$teamId)>
                        <option value="">— Select member —</option>
                        @foreach($membersForForm as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>
                    @error('assignedTo') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    @error('assignedTo.*') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Priority --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <select wire:model="priority"
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>

                {{-- Status (edit only) --}}
                @if($editingId)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select wire:model="status"
                                class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="review">Review</option>
                            <option value="done">Done</option>
                        </select>
                    </div>
                @endif

                {{-- Actual start date (edit only) --}}
                @if($editingId)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Actual Start Date</label>
                        <input wire:model="startDate" type="date"
                               class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                @endif

                {{-- Due Date --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Due Date <span class="text-red-500">*</span>
                    </label>
                    <input wire:model="dueDate" type="date"
                           class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('dueDate') border-red-400 @enderror">
                    @error('dueDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-3 mt-6">
                <button wire:click="save"
                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    {{ $editingId ? 'Update Task' : 'Assign Task' }}
                </button>
                <button wire:click="cancelForm"
                        class="px-5 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
            </div>
        </div>
    @endif

    {{-- ── Task list ─────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" @if(!$showForm) wire:poll.visible.30s @endif>
        @if($tasks->isEmpty())
            <div class="py-16 text-center text-gray-400">
                <svg class="w-10 h-10 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                </svg>
                <p class="text-sm">No tasks found. Assign one to get started.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Task</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Assigned To</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Due Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Priority</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($tasks as $task)
                        @php
                            $isOverdue = $task->isExceededDeadline();

                            $priorityBadge = match($task->priority) {
                                'high'   => 'bg-red-100 text-red-700',
                                'medium' => 'bg-yellow-100 text-yellow-700',
                                default  => 'bg-gray-100 text-gray-500',
                            };

                            $statusOptions = [
                                'pending'     => 'Pending',
                                'in_progress' => 'In Progress',
                                'review'      => 'Review',
                                'done'        => 'Done',
                            ];
                            $statusColor = match ($task->status) {
                                'pending'     => 'bg-gray-100 text-gray-600',
                                'in_progress' => 'bg-blue-100 text-blue-700',
                                'review'      => 'bg-amber-100 text-amber-800',
                                'done'        => 'bg-green-100 text-green-700',
                                default       => 'bg-gray-100 text-gray-500',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 transition {{ $isOverdue ? 'bg-red-50 hover:bg-red-50' : '' }}">
                            <td class="px-6 py-4">
                                <p class="font-medium text-gray-900">{{ $task->title }}</p>
                                @if($task->description)
                                    <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ $task->description }}</p>
                                @endif
                                <p class="text-xs text-gray-400 mt-0.5">{{ $task->team->name }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                        {{ strtoupper(substr($task->assignees->first()?->name ?? $task->assignee?->name ?? '?', 0, 1)) }}
                                    </div>
                                    <span class="text-gray-700">
                                        {{ $task->assignees->isNotEmpty() ? $task->assignees->pluck('name')->join(', ') : $task->assignee?->name }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="{{ $isOverdue ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                    {{ $task->due_date ? $task->due_date->format('M d, Y') : '—' }}
                                </span>
                                @if($isOverdue)
                                    <span class="block text-xs text-red-400">Overdue</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $priorityBadge }}">
                                    {{ ucfirst($task->priority) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                {{-- Inline status switcher --}}
                                <select wire:change="updateStatus({{ $task->id }}, $event.target.value)"
                                        class="text-xs border border-gray-200 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-indigo-500 {{ $statusColor }}">
                                    @foreach($statusOptions as $val => $label)
                                        <option value="{{ $val }}" {{ $task->status === $val ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                @if($expandedTaskId !== $task->id)
                                    <button wire:click="toggleTaskDetails({{ $task->id }})"
                                            class="text-indigo-600 hover:text-indigo-800 text-xs font-medium mr-3 transition">
                                        Details
                                    </button>
                                @else
                                    <button wire:click="toggleTaskDetails({{ $task->id }})"
                                            class="text-gray-600 hover:text-gray-800 text-xs font-medium mr-3 transition">
                                        Hide
                                    </button>
                                @endif
                                <button wire:click="openEdit({{ $task->id }})"
                                        class="text-indigo-600 hover:text-indigo-800 text-xs font-medium mr-3 transition">
                                    Edit
                                </button>
                                
                                <button wire:click="confirmDelete({{ $task->id }})"
                                        class="text-red-500 hover:text-red-700 text-xs font-medium transition">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        @if($expandedTaskId === $task->id)
                            @php
                                $memberProgress = $task->memberProgress ?? collect();
                                $progressCounts = [
                                    'pending' => $memberProgress->where('status', 'pending')->count(),
                                    'in_progress' => $memberProgress->where('status', 'in_progress')->count(),
                                    'review' => $memberProgress->where('status', 'review')->count(),
                                    'done' => $memberProgress->where('status', 'done')->count(),
                                ];
                                $progressLabel = fn (string $status) => match ($status) {
                                    'pending' => 'Pending',
                                    'in_progress' => 'In Progress',
                                    'review' => 'Review',
                                    'done' => 'Done',
                                    default => ucfirst($status),
                                };
                                $progressClass = fn (string $status) => match ($status) {
                                    'pending' => 'bg-gray-100 text-gray-600',
                                    'in_progress' => 'bg-blue-100 text-blue-700',
                                    'review' => 'bg-amber-100 text-amber-800',
                                    'done' => 'bg-green-100 text-green-700',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <tr>
                                <td colspan="6" class="bg-gray-50 px-6 py-4">
                                    <div class="rounded-xl border border-gray-200 bg-white p-4 space-y-4">
                                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div class="min-w-0 space-y-2">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-base font-semibold text-gray-900">Task details</p>
                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                                        {{ $statusOptions[$task->status] ?? ucfirst($task->status) }}
                                                    </span>
                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $priorityBadge }}">
                                                        {{ ucfirst($task->priority) }} priority
                                                    </span>
                                                    @if($isOverdue)
                                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                                            Overdue
                                                        </span>
                                                    @endif
                                                </div>
                                                <p class="text-sm text-gray-600 whitespace-pre-line">
                                                    {{ $task->description ?: 'No description provided.' }}
                                                </p>
                                                <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500">
                                                    <span><span class="font-semibold text-gray-700">Project:</span> {{ $task->project?->name ?? 'No project' }}</span>
                                                    <span><span class="font-semibold text-gray-700">Team:</span> {{ $task->team?->name ?? 'No team' }}</span>
                                                    <span><span class="font-semibold text-gray-700">Start:</span> {{ $task->start_date?->format('M d, Y') ?? 'Not set' }}</span>
                                                    <span><span class="font-semibold text-gray-700">Due:</span> {{ $task->due_date?->format('M d, Y') ?? 'Not set' }}</span>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:w-[28rem]">
                                                <div class="rounded-lg bg-gray-50 p-3 text-center">
                                                    <p class="text-lg font-bold text-gray-800">{{ $memberProgress->count() }}</p>
                                                    <p class="text-xs font-medium text-gray-500">Members</p>
                                                </div>
                                                <div class="rounded-lg bg-gray-50 p-3 text-center">
                                                    <p class="text-lg font-bold text-blue-700">{{ $progressCounts['in_progress'] }}</p>
                                                    <p class="text-xs font-medium text-blue-700">In Progress</p>
                                                </div>
                                                <div class="rounded-lg bg-amber-50 p-3 text-center">
                                                    <p class="text-lg font-bold text-amber-800">{{ $progressCounts['review'] }}</p>
                                                    <p class="text-xs font-medium text-amber-800">Review</p>
                                                </div>
                                                <div class="rounded-lg bg-green-50 p-3 text-center">
                                                    <p class="text-lg font-bold text-green-700">{{ $progressCounts['done'] }}</p>
                                                    <p class="text-xs font-medium text-green-700">Done</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Member review status</p>
                                            @if($memberProgress->isEmpty())
                                                <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-400">No member progress recorded yet.</p>
                                            @else
                                                <div class="overflow-hidden rounded-lg border border-gray-200">
                                                    <table class="w-full text-sm">
                                                        <thead class="bg-gray-50 text-gray-500">
                                                            <tr>
                                                                <th class="px-3 py-2 text-left font-semibold">Member</th>
                                                                <th class="px-3 py-2 text-left font-semibold">Status</th>
                                                                <th class="px-3 py-2 text-left font-semibold">Started</th>
                                                                <th class="px-3 py-2 text-left font-semibold">Completed</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-100 bg-white">
                                                            @foreach($memberProgress as $progress)
                                                                <tr>
                                                                    <td class="px-3 py-2 text-gray-700">{{ $progress->user?->name ?? 'Unknown member' }}</td>
                                                                    <td class="px-3 py-2">
                                                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $progressClass($progress->status) }}">
                                                                            {{ $progressLabel($progress->status) }}
                                                                        </span>
                                                                    </td>
                                                                    <td class="px-3 py-2 text-gray-500">{{ $progress->started_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                                                    <td class="px-3 py-2 text-gray-500">{{ $progress->completed_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif
                                        </div>

                                        <livewire:task-discussion :task-id="$task->id" :key="'lead-task-discussion-'.$task->id" />
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
            Delete task?
        </x-slot>

        <x-slot name="content">
            This task will be permanently removed from the project.
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
