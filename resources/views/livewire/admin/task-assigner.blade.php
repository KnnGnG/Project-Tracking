<div wire:poll.visible.15s>
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-gray-900">Task oversight</h1>
        <p class="text-sm text-gray-500 mt-1">
            View all tasks across projects. Assignments are managed by team leads from
            <span class="font-medium text-gray-600">Lead → Manage Tasks</span>.
        </p>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-4 mb-6" role="group" aria-label="Filter tasks">
        <div class="flex flex-col gap-1 min-w-[10rem]">
            <label for="task-oversight-filter-status" class="text-xs font-medium text-gray-700">
                Status
            </label>
            <select id="task-oversight-filter-status"
                    wire:model.live="filterStatus"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="in_progress">In progress</option>
                <option value="done">Done</option>
            </select>
        </div>

        <div class="flex flex-col gap-1 min-w-[12rem]">
            <label for="task-oversight-filter-project" class="text-xs font-medium text-gray-700">
                Project
            </label>
            <select id="task-oversight-filter-project"
                    wire:model.live="filterProject"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All projects</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1 min-w-[9rem]">
            <label for="task-oversight-per-page" class="text-xs font-medium text-gray-700">
                Per page
            </label>
            <select id="task-oversight-per-page"
                    wire:model.live="perPage"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="15">15 per page</option>
                <option value="20">20 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
        </div>
    </div>

    {{-- Tasks table (read-only) --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        @if($tasks->isEmpty())
            <div class="py-16 text-center text-gray-400">
                <p class="text-sm">No tasks match your filters.</p>
            </div>
        @else
            <p class="px-6 py-3 text-xs text-gray-500 border-b border-gray-100 bg-gray-50/80">
                Showing
                <span class="font-medium text-gray-700">{{ $tasks->firstItem() }}</span>
                –
                <span class="font-medium text-gray-700">{{ $tasks->lastItem() }}</span>
                of
                <span class="font-medium text-gray-700">{{ $tasks->total() }}</span>
                tasks
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[800px]">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left font-semibold text-gray-600">Task</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-600">Project / team</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-600">Assigned to</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-600">Created by</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-600">Due date</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-600">Priority</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-600">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($tasks as $task)
                            @php
                                $exceeded = $task->isExceededDeadline();
                            @endphp
                            <tr class="hover:bg-gray-50 transition {{ $exceeded ? 'bg-red-50/80 hover:bg-red-50' : '' }}">
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
                                <td class="px-6 py-4 text-gray-700">{{ $task->assignee?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-600">
                                    {{ $task->creator?->name ?? '—' }}
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
