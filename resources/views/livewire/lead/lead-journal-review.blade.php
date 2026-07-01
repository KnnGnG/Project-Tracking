<div class="space-y-6" wire:poll.visible.60s>
    <div class="ui-page-heading">
        <div>
            <h2>Journal Review</h2>
            <p>Review team logs, general work, and task-specific time entries.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm lg:grid-cols-5">
        <div>
            <label for="logDate" class="sr-only">Log date</label>
            <input id="logDate" type="date" wire:model.live="logDate" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="teamId" class="sr-only">Team</label>
            <select id="teamId" wire:model.live="teamId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="">All teams</option>
            @foreach($leadTeams as $team)
                <option value="{{ $team->id }}">{{ $team->name }} / {{ $team->project->name }}</option>
            @endforeach
            </select>
        </div>

        <div>
            <label for="memberId" class="sr-only">Member</label>
            <select id="memberId" wire:model.live="memberId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="">All members</option>
            @foreach($members as $member)
                <option value="{{ $member->id }}">{{ $member->name }}</option>
            @endforeach
            </select>
        </div>

        <div class="lg:col-span-2">
            <label for="taskId" class="sr-only">Task</label>
            <select id="taskId" wire:model.live="taskId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="">All tasks</option>
            @foreach($tasks as $task)
                <option value="{{ $task->id }}">{{ $task->title }}</option>
            @endforeach
            </select>
        </div>
    </div>

    <div class="ui-soft-panel px-5 py-4">
        <p class="text-sm text-gray-500">Logged time</p>
        <p class="mt-1 text-3xl font-extrabold text-gray-900">{{ intdiv($totalMinutes, 60) }}h {{ $totalMinutes % 60 }}m</p>
    </div>

    <div class="ui-soft-panel overflow-hidden">
        @forelse($logs as $log)
            <article class="border-b border-gray-100 px-5 py-4 last:border-b-0">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $log->task?->title ?? 'General work' }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $log->user?->name ?? 'Deleted user' }}
                            / {{ $log->task?->team?->name ?? $log->team?->name ?? 'No team' }}
                            / {{ $log->task?->project?->name ?? $log->team?->project?->name ?? 'No project' }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-800">{{ intdiv($log->minutes, 60) }}h {{ $log->minutes % 60 }}m</p>
                        <p class="text-xs text-gray-400">{{ $log->log_date->format('M d, Y') }}</p>
                    </div>
                </div>

                @if($log->notes)
                    <div class="mt-3 rounded-lg bg-gray-50 px-3 py-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Journal notes</p>
                        <p class="mt-1 text-sm leading-6 text-gray-700 whitespace-pre-line">{{ $log->notes }}</p>
                    </div>
                @endif
            </article>
        @empty
            <div class="py-16 text-center text-sm text-gray-400">
                No journal logs found for these filters.
            </div>
        @endforelse
    </div>

    {{ $logs->links() }}
</div>
