<div class="space-y-6">

    <div class="ui-toolbar">
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="filterTeamId"
                    aria-label="Team"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All My Teams</option>
                @foreach($leadTeams as $team)
                    @php
                        $activeProjectId = (int) session('active_project_id', 0);
                        $teamProject = $activeProjectId > 0
                            ? $team->assignedProjects()->firstWhere('id', $activeProjectId)
                            : $team->assignedProjects()->first();
                    @endphp
                    <option value="{{ $team->id }}">{{ $team->name }}@if($teamProject) - {{ $teamProject->name }}@endif</option>
                @endforeach
            </select>

            <select wire:model.live="filterStatus"
                    aria-label="Status"
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
                    wire:loading.attr="disabled"
                    wire:target="openCreate"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition disabled:opacity-60 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Assign Task
            </button>
        @endif
    </div>

    {{-- ── Task form ─────────────────────────────────────────────────────────── --}}
    @if($showForm)
        <div x-data="unsavedFormGuard()" @input="markDirty" @change="markDirty" @beforeunload.window="warn($event)" class="ui-soft-panel p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-5">
                {{ $editingId ? 'Edit Task' : 'Assign New Task' }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2 border-b border-slate-100 pb-2">
                    <h3 class="text-sm font-bold text-slate-800">Task details</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Describe the outcome the assignees need to deliver.</p>
                </div>
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

                <div class="md:col-span-2 mt-2 border-b border-slate-100 pb-2">
                    <h3 class="text-sm font-bold text-slate-800">Assignment</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Choose the team and the members responsible for this task.</p>
                </div>

                {{-- Team --}}
                <div>
                    @php
                        $selectedFormTeam = $teamId ? $leadTeams->firstWhere('id', (int) $teamId) : null;
                        $selectedFormProject = $selectedFormTeam ? ($selectedFormTeam->assignedProjects()->firstWhere('id', (int) session('active_project_id', 0)) ?? $selectedFormTeam->assignedProjects()->first()) : null;
                    @endphp
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
                    @if($selectedFormProject)
                        <div class="mt-2 rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-xs text-indigo-800">
                            <p class="font-semibold">{{ $selectedFormProject->name }}</p>
                            <p class="mt-0.5">
                                Project starts {{ $selectedFormProject->start_date?->format('M d, Y') ?? 'Not set' }}
                            </p>
                        </div>
                    @endif
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

                <div class="md:col-span-2 mt-2 border-b border-slate-100 pb-2">
                    <h3 class="text-sm font-bold text-slate-800">Schedule and priority</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Set urgency and the expected delivery window.</p>
                </div>

                <div class="md:col-span-2 lg:col-span-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
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

                    {{-- Start Date --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input wire:model="startDate" type="date"
                               class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('startDate') border-red-400 @enderror">
                        @error('startDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

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

                <div class="md:col-span-2 mt-2 border-b border-slate-100 pb-2">
                    <h3 class="text-sm font-bold text-slate-800">Supporting files</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Add references the assignees need to complete the work.</p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Attachments</label>
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-sm font-medium text-gray-600 transition hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828L18 9.828a4 4 0 00-5.657-5.657L5.757 10.757a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                        <span>Choose files</span>
                        <input wire:model="newAttachments"
                               wire:key="task-attachments-{{ $uploadIteration }}"
                               type="file"
                               multiple
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.webp,.zip"
                               class="sr-only">
                    </label>
                    <p class="mt-1 text-xs text-gray-400">Up to 5 files, 10 MB each.</p>
                    <div wire:loading wire:target="newAttachments" class="mt-2 text-xs font-medium text-indigo-600">Uploading files...</div>
                    @error('newAttachments') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    @error('newAttachments.*') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror

                    @if(!empty($newAttachments))
                        <div class="mt-3 space-y-2">
                            @foreach($newAttachments as $index => $file)
                                <div wire:key="pending-{{ $index }}" class="flex items-center justify-between gap-3 rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-sm">
                                    <span class="min-w-0 truncate text-indigo-900">{{ $file->getClientOriginalName() }}</span>
                                    <button type="button" wire:click="removePendingAttachment({{ $index }})" class="shrink-0 text-xs font-semibold text-red-600 hover:text-red-700">Remove</button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($existingAttachments->isNotEmpty())
                        <div class="mt-3 space-y-2">
                            @foreach($existingAttachments as $attachment)
                                <div wire:key="existing-{{ $attachment->id }}" class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                    <button type="button" wire:click="downloadAttachment({{ $attachment->id }})" class="min-w-0 truncate font-medium text-indigo-700 hover:underline">
                                        {{ $attachment->original_name }}
                                    </button>
                                    <div class="flex shrink-0 items-center gap-3">
                                        <span class="text-xs text-gray-400">{{ $attachment->formattedSize() }}</span>
                                        <button type="button" wire:click="removeAttachment({{ $attachment->id }})" wire:confirm="Remove this attachment?" class="text-xs font-semibold text-red-600 hover:text-red-700">Remove</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3 mt-6">
                <button wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update Task' : 'Assign Task' }}</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
                <button wire:click="cancelForm" @click="if (!confirmLeave()) $event.stopImmediatePropagation()"
                        wire:loading.attr="disabled"
                        wire:target="cancelForm,save"
                        class="px-5 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition disabled:opacity-60 disabled:cursor-not-allowed">
                    Cancel
                </button>
            </div>
        </div>
    @endif

    {{-- ── Task list ─────────────────────────────────────────────────────────── --}}
    <div class="ui-soft-panel relative overflow-hidden" @if(!$showForm) wire:poll.visible.30s @endif>
        <x-loading-skeleton wire:loading.delay class="ui-loading-overlay" wire:target="filterTeamId,filterStatus,openCreate,edit,save,delete" />
        @if($tasks->isEmpty())
            <div class="ui-empty-state">
                <svg class="w-10 h-10 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                </svg>
                <p class="text-sm">{{ $filterTeamId || $filterStatus ? 'No tasks match the selected filters.' : 'No tasks are assigned in this workspace yet.' }}</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Task</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Assigned To</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Start Date</th>
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
                            $taskStartTime = $task->start_time ? \Illuminate\Support\Carbon::parse($task->start_time)->format('h:i A') : null;
                        @endphp
                        <tr wire:click="toggleTaskDetails({{ $task->id }})"
                            wire:keydown.enter="toggleTaskDetails({{ $task->id }})"
                            tabindex="0"
                            aria-label="View details for {{ $task->title }}"
                            class="ui-clickable-row hover:bg-gray-50 transition {{ $isOverdue ? 'bg-red-50 hover:bg-red-50' : '' }}">
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
                                <span class="text-gray-600">
                                    {{ $task->start_date ? $task->start_date->format('M d, Y') : 'Not set' }}
                                </span>
                                @if($taskStartTime)
                                    <span class="block text-xs text-gray-400">{{ $taskStartTime }}</span>
                                @endif
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
                                <x-status-badge :status="$task->status" :label="$statusOptions[$task->status] ?? null" />
                                <span class="mt-1 block text-[11px] text-gray-400">From members</span>
                            </td>
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                @if($expandedTaskId !== $task->id)
                                    <button wire:click.stop="toggleTaskDetails({{ $task->id }})"
                                            class="ui-action-button ui-action-primary mr-2">
                                        Details
                                    </button>
                                @else
                                    <button wire:click.stop="toggleTaskDetails({{ $task->id }})"
                                            class="ui-action-button mr-2">
                                        Hide
                                    </button>
                                @endif
                                <button wire:click.stop="openEdit({{ $task->id }})"
                                        class="ui-action-button ui-action-primary mr-2">
                                    Edit
                                </button>

                                <span class="ml-1 border-l border-slate-200 pl-3">
                                    <button wire:click.stop="confirmDelete({{ $task->id }})"
                                            class="ui-action-button ui-action-danger">
                                        Delete
                                    </button>
                                </span>
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
                                <td colspan="7" class="bg-gray-50 px-6 py-4">
                                    <div class="rounded-xl border border-gray-200 bg-white p-4 space-y-4">
                                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div class="min-w-0 space-y-2">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-base font-semibold text-gray-900">Task details</p>
                                                    <x-status-badge :status="$task->status" :label="$statusOptions[$task->status] ?? null" />
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
                                                    <span>
                                                        <span class="font-semibold text-gray-700">Scheduled Start:</span>
                                                        {{ $task->start_date?->format('M d, Y') ?? 'Not set' }}{{ $taskStartTime ? ' at '.$taskStartTime : '' }}
                                                    </span>
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
                                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Attachments</p>
                                            @if($task->attachments->isEmpty())
                                                <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-400">No files attached.</p>
                                            @else
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach($task->attachments as $attachment)
                                                        <button wire:key="task-attachment-{{ $attachment->id }}" type="button" wire:click="downloadAttachment({{ $attachment->id }})" class="inline-flex max-w-full items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                                                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828L18 9.828a4 4 0 00-5.657-5.657L5.757 10.757a6 6 0 108.486 8.486L20.5 13"/>
                                                            </svg>
                                                            <span class="truncate">{{ $attachment->original_name }}</span>
                                                            <span class="shrink-0 text-xs font-normal text-gray-400">{{ $attachment->formattedSize() }}</span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
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
                                                                <th class="px-3 py-2 text-left font-semibold">Actions</th>
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
                                                                    <td class="px-3 py-2">
                                                                        @if($progress->status === 'review')
                                                                            <button type="button"
                                                                                    wire:click="approveMemberReview({{ $task->id }}, {{ $progress->user_id }})"
                                                                                    wire:loading.attr="disabled"
                                                                                    wire:target="approveMemberReview({{ $task->id }}, {{ $progress->user_id }})"
                                                                                    class="rounded-lg bg-green-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-green-700 disabled:opacity-60 disabled:cursor-not-allowed">
                                                                                Approve &amp; mark done
                                                                            </button>
                                                                        @else
                                                                            <span class="text-gray-300">—</span>
                                                                        @endif
                                                                    </td>
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

            <x-danger-button class="ms-3" wire:click="deleteConfirmed" wire:loading.attr="disabled" wire:target="deleteConfirmed">
                <span wire:loading.remove wire:target="deleteConfirmed">Delete</span>
                <span wire:loading wire:target="deleteConfirmed">Deleting...</span>
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>

</div>

