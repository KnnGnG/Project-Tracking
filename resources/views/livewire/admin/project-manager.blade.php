<div>
    {{-- Flash message --}}
    @if(session('success'))
        <x-floating-notification :message="session('success')" />
    @endif
    @if(session('error'))
        <x-floating-notification :message="session('error')" type="error" />
    @endif

    <div class="ui-toolbar">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-900">Projects</p>
            <p class="mt-0.5 text-xs text-gray-400">Search and manage project records.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <input wire:model.live.debounce.300ms="search"
                   type="text"
                   placeholder="Search projects..."
                   class="w-64 max-w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">

            @if(!$showForm)
                <button wire:click="openCreate"
                        wire:loading.attr="disabled"
                        wire:target="openCreate"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Project
                </button>
            @endif
        </div>
    </div>

    @if($pendingStatusRequests->isNotEmpty())
        <section class="mb-5 overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-amber-100 bg-amber-50 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Project status requests</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Review lifecycle changes submitted by team leads.</p>
                </div>
                <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-amber-200 px-2 py-1 text-xs font-bold text-amber-800">
                    {{ $pendingStatusRequestCount }}
                </span>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($pendingStatusRequests as $statusRequest)
                    <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-gray-900">{{ $statusRequest->project?->name ?? 'Deleted project' }}</p>
                                <span class="text-xs font-medium text-gray-400">
                                    {{ ucwords(str_replace('_', ' ', $statusRequest->requested_from_status ?? $statusRequest->project?->status ?? 'unknown')) }}
                                    &rarr;
                                </span>
                                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                                    {{ ucwords(str_replace('_', ' ', $statusRequest->requested_status)) }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ $statusRequest->requester?->name ?? 'Unknown user' }}: {{ $statusRequest->reason }}
                            </p>
                            <p class="mt-1 text-[11px] text-gray-400">Submitted {{ $statusRequest->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="w-full sm:w-72">
                            <label for="request-review-reason-{{ $statusRequest->id }}" class="sr-only">Review note for {{ $statusRequest->project?->name ?? 'project status request' }}</label>
                            <input id="request-review-reason-{{ $statusRequest->id }}"
                                   type="text"
                                   wire:model="requestReviewReasons.{{ $statusRequest->id }}"
                                   maxlength="500"
                                   placeholder="Review note (required to decline)"
                                   class="w-full rounded-lg border-gray-300 px-3 py-1.5 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                            @error('requestReviewReasons.'.$statusRequest->id)
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button"
                                    wire:click="approveStatusRequest({{ $statusRequest->id }})"
                                    wire:loading.attr="disabled"
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60">
                                Approve
                            </button>
                            <button type="button"
                                    wire:click="rejectStatusRequest({{ $statusRequest->id }})"
                                    wire:loading.attr="disabled"
                                    class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:opacity-60">
                                Decline
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($pendingStatusRequestCount > $pendingStatusRequests->count())
                <p class="border-t border-amber-100 bg-amber-50/60 px-4 py-2 text-xs text-amber-700">
                    Showing the oldest {{ $pendingStatusRequests->count() }} of {{ $pendingStatusRequestCount }} pending requests.
                </p>
            @endif
        </section>
    @endif

    {{-- Create / Edit form --}}
    @if($showForm)
        <div x-data="unsavedFormGuard()" @input="markDirty" @change="markDirty" @beforeunload.window="warn($event)" class="ui-soft-panel p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-800 mb-5">
                {{ $editingId ? 'Edit Project' : 'New Project' }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2 border-b border-slate-100 pb-2">
                    <h3 class="text-sm font-bold text-slate-800">Project details</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Name, schedule, lifecycle status, and client ownership.</p>
                </div>
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

                @if($editingId)
                    <div>
                        <label for="status-change-reason" class="block text-sm font-medium text-gray-700 mb-1">Status change reason</label>
                        <input id="status-change-reason"
                               wire:model="statusChangeReason"
                               type="text"
                               maxlength="500"
                               placeholder="Required when changing the lifecycle status"
                               class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('statusChangeReason') border-red-400 @enderror">
                        @error('statusChangeReason') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                @endif

                {{-- Client --}}
                <div class="{{ $editingId ? 'md:col-span-2' : '' }}">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client (optional)</label>
                    <select wire:model="clientId"
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">— No client —</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2 mt-2 border-b border-slate-100 pb-2">
                    <h3 class="text-sm font-bold text-slate-800">Team assignment</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Attach existing teams that will work in this project.</p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assign Existing Teams</label>
                    <div class="rounded-lg border border-gray-300 bg-white p-3">
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <input type="text"
                                   wire:model.live.debounce.300ms="teamSearch"
                                   placeholder="Search teams..."
                                   class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="grid max-h-52 gap-2 overflow-y-auto pr-1 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($projectTeamOptions as $teamOption)
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-sm transition hover:bg-indigo-50">
                                    <input type="checkbox"
                                           wire:model="projectTeamIds"
                                           value="{{ $teamOption->id }}"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium text-gray-800">{{ $teamOption->name }}</span>
                                        <span class="block text-xs text-gray-500">
                                            {{ $teamOption->projects->pluck('name')->join(', ') ?: ($teamOption->project?->name ?? 'No project') }} · {{ $teamOption->lead?->name ?? 'No lead' }}
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('projectTeamIds') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        @error('projectTeamIds.*') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    @if(!empty($selectedProjectTeams))
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($selectedProjectTeams as $teamOption)
                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">
                                    {{ $teamOption->name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-3 mt-6">
                <button wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update Project' : 'Create Project' }}</span>
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

    {{-- Projects table --}}
    <div class="ui-soft-panel relative overflow-hidden" @if(!$showForm) wire:poll.visible.60s @endif>
        <x-loading-skeleton wire:loading.delay class="ui-loading-overlay" wire:target="search,openCreate,openEdit,save,confirmDelete" />
        @if($projects->isEmpty())
            <div class="ui-empty-state">
                <svg class="w-10 h-10 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M3 7h18M3 12h18M3 17h18"/>
                </svg>
                <p class="text-sm">{{ filled($search) ? 'No projects match your search.' : 'No projects have been created yet.' }}</p>
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
                        <tr wire:click="showDetails({{ $project->id }})"
                            wire:keydown.enter="showDetails({{ $project->id }})"
                            tabindex="0"
                            aria-label="View details for {{ $project->name }}"
                            class="ui-clickable-row hover:bg-gray-50 transition">
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
                                    $effectiveProjectStatus = $project->effectiveStatus();
                                    $badge = match($effectiveProjectStatus) {
                                        'active' => 'bg-emerald-100 text-emerald-700',
                                        'overdue' => 'bg-red-100 text-red-700',
                                        'near_due' => 'bg-amber-100 text-amber-700',
                                        'upcoming' => 'bg-sky-100 text-sky-700',
                                        'on_hold' => 'bg-slate-200 text-slate-700',
                                        'completed' => 'bg-indigo-100 text-indigo-700',
                                        default => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <x-status-badge :status="$effectiveProjectStatus" :label="$project->effectiveStatusLabel()" />
                            </td>
                            <td class="px-6 py-4">
                                @php $pct = $project->completionPercentage() @endphp
                                <button type="button"
                                        wire:click.stop="toggleProgressDetails({{ $project->id }})"
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
                                <div class="flex flex-wrap items-center justify-start gap-2">
                                    <button wire:click.stop="showDetails({{ $project->id }})"
                                            class="ui-action-button ui-action-primary">
                                        Details
                                    </button>
                                    <button wire:click.stop="openEdit({{ $project->id }})"
                                            class="ui-action-button ui-action-primary">
                                        Edit
                                    </button>
                                    <span class="ml-1 border-l border-slate-200 pl-3">
                                        <button wire:click.stop="confirmDelete({{ $project->id }})"
                                                class="ui-action-button ui-action-danger">
                                            Delete
                                        </button>
                                    </span>
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
                                    'active' => 'bg-emerald-100 text-emerald-700',
                                    'overdue' => 'bg-red-100 text-red-700',
                                    'near_due' => 'bg-amber-100 text-amber-700',
                                    'upcoming' => 'bg-sky-100 text-sky-700',
                                    'on_hold' => 'bg-slate-200 text-slate-700',
                                    'completed' => 'bg-indigo-100 text-indigo-700',
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
                                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass($detailsProject->effectiveStatus()) }}">
                                                            {{ $detailsProject->effectiveStatusLabel() }}
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

                                        @if($detailsProject->statusHistories->isNotEmpty())
                                            <div class="border-y border-gray-100 py-3">
                                                <h4 class="text-xs font-semibold uppercase text-gray-500">Status history</h4>
                                                <div class="mt-2 divide-y divide-gray-100">
                                                    @foreach($detailsProject->statusHistories as $history)
                                                        <div class="flex flex-wrap items-start justify-between gap-2 py-2 text-xs">
                                                            <div>
                                                                <p class="font-semibold text-gray-700">
                                                                    {{ $history->from_status ? ucwords(str_replace('_', ' ', $history->from_status)) : 'Created' }}
                                                                    &rarr;
                                                                    {{ ucwords(str_replace('_', ' ', $history->to_status)) }}
                                                                </p>
                                                                <p class="mt-0.5 text-gray-500">{{ $history->reason }}</p>
                                                            </div>
                                                            <div class="text-right text-gray-400">
                                                                <p>{{ $history->actor?->name ?? 'System' }} &middot; {{ ucfirst($history->source) }}</p>
                                                                <p>{{ $history->created_at->format('M d, Y h:i A') }}</p>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

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
            <div class="border-t border-gray-100 bg-gray-50 px-6 py-4">
                {{ $projects->links() }}
            </div>
        @endif
    </div>

    

    <x-confirmation-modal wire:model="confirmingDelete" maxWidth="md">
        <x-slot name="title">
            Delete project?
        </x-slot>

        <x-slot name="content">
            This will remove the project, its project assignments, and all related tasks. Shared teams will remain available for other projects.
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
