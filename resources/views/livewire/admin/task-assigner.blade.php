<div class="space-y-4" wire:poll.visible.60s>
    {{-- Filters --}}
    <div class="sticky z-30 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm" style="top: 0.75rem;" role="group" aria-label="Filter tasks">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-900">Task Oversight</p>
            <p class="mt-0.5 text-xs text-gray-400">Filter tasks by status, project, and page size.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <select id="task-oversight-filter-status"
                    wire:model.live="filterStatus"
                    aria-label="Status"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="in_progress">In progress</option>
                <option value="review">Review</option>
                <option value="done">Done</option>
            </select>

            <select id="task-oversight-filter-project"
                    wire:model.live="filterProject"
                    aria-label="Project"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All projects</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>

            <select id="task-oversight-per-page"
                    wire:model.live="perPage"
                    aria-label="Per page"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="15">15 per page</option>
                <option value="20">20 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
        </div>
    </div>
    {{-- Tasks table (read-only) --}}
    <div class="ui-soft-panel overflow-hidden">
        @if($tasks->isEmpty())
            <div class="ui-empty-state">
                <p class="text-sm font-semibold text-gray-700">No tasks match your filters.</p>
                <p class="mt-1 text-sm text-gray-500">Try changing the status or project filter.</p>
            </div>
        @else
            <p class="border-b border-gray-100 bg-gray-50/80 px-6 py-3 text-xs text-gray-500">
                Showing
                <span class="font-medium text-gray-700">{{ $tasks->firstItem() }}</span>
                &ndash;
                <span class="font-medium text-gray-700">{{ $tasks->lastItem() }}</span>
                of
                <span class="font-medium text-gray-700">{{ $tasks->total() }}</span>
                tasks
            </p>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1120px] text-sm" style="table-layout: fixed;">
                    <thead class="block border-b border-gray-200 bg-gray-50">
                        <tr style="display: table; width: 100%; table-layout: fixed;">
                            <th class="bg-gray-50 px-6 py-3 text-left font-semibold text-gray-600" style="width: 4.5rem;">No.</th>
                            <th class="bg-gray-50 px-6 py-3 text-left font-semibold text-gray-600">Task</th>
                            <th class="bg-gray-50 px-6 py-3 text-left font-semibold text-gray-600">Project / team</th>
                            <th class="bg-gray-50 px-6 py-3 text-left font-semibold text-gray-600">Assigned / actual start</th>
                            <th class="bg-gray-50 px-6 py-3 text-left font-semibold text-gray-600">Created by</th>
                            <th class="bg-gray-50 px-6 py-3 text-left font-semibold text-gray-600">Scheduled start</th>
                            <th class="bg-gray-50 px-6 py-3 text-left font-semibold text-gray-600">Due date</th>
                            <th class="bg-gray-50 px-6 py-3 text-left font-semibold text-gray-600">Priority</th>
                            <th class="bg-gray-50 px-6 py-3 text-left font-semibold text-gray-600">Status</th>
                        </tr>
                    </thead>
                    <tbody class="block divide-y divide-gray-100 overflow-y-auto" style="max-height: calc(100vh - 22rem);">
                        @foreach($tasks as $task)
                            @php
                                $exceeded = $task->isExceededDeadline();
                                $assignees = $task->getAllAssignees();
                                $progressByUser = $task->memberProgress->keyBy('user_id');
                                $scheduledStart = $task->start_date
                                    ? $task->start_date->format('M d, Y') . ($task->start_time ? ' ' . \Illuminate\Support\Str::of((string) $task->start_time)->substr(0, 5) : '')
                                    : null;
                            @endphp
                            <tr class="hover:bg-gray-50 transition {{ $exceeded ? 'bg-red-50/80 hover:bg-red-50' : '' }}" style="display: table; width: 100%; table-layout: fixed;">
                                <td class="px-6 py-4 font-semibold text-gray-500" style="width: 4.5rem;">
                                    {{ $tasks->firstItem() + $loop->index }}
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-900">{{ $task->title }}</p>
                                    @if($task->description)
                                        <p class="text-xs text-gray-400 mt-0.5 line-clamp-2">{{ $task->description }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <p>{{ $task->project?->name ?? '—' }}</p>
                                    <p class="text-xs text-gray-400">{{ $task->team?->name ?? '—' }}</p>
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    @if($assignees->isEmpty())
                                        <span class="text-gray-400">—</span>
                                    @else
                                        <div class="space-y-2">
                                            @foreach($assignees as $member)
                                                @php $actualStart = $progressByUser->get($member->id)?->started_at; @endphp
                                                <div>
                                                    <p class="font-medium text-gray-800">{{ $member->name }}</p>
                                                    <p class="text-xs {{ $actualStart ? 'text-emerald-700' : 'text-gray-400' }}">
                                                        Actual: {{ $actualStart?->format('M d, Y h:i A') ?? 'Not started' }}
                                                    </p>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    {{ $task->creator?->name ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                    {{ $scheduledStart ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="{{ $exceeded ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                        {{ $task->due_date?->format('M d, Y') ?? '—' }}
                                    </span>
                                    @if($exceeded)
                                        <span class="ml-1 text-xs text-red-500">Overdue</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $priorityBadge = match($task->priority) {
                                            'high'   => 'bg-red-100 text-red-700',
                                            'medium' => 'bg-yellow-100 text-yellow-700',
                                            'low'    => 'bg-gray-100 text-gray-600',
                                            default  => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $priorityBadge }}">
                                        {{ ucfirst($task->priority) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $statusBadge = match($task->status) {
                                            'pending'     => 'bg-gray-100 text-gray-600',
                                            'in_progress' => 'bg-blue-100 text-blue-700',
                                            'review' => 'bg-amber-100 text-amber-800',
                                            'done'        => 'bg-green-100 text-green-700',
                                            default       => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge }}">
                                        {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                {{ $tasks->links() }}
            </div>
        @endif
    </div>
</div>
