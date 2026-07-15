<div class="space-y-6" wire:poll.visible.30s>
    <div class="ui-toolbar">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-900">Journal Review</p>
            <p class="mt-0.5 text-xs text-gray-400">Filter logs by date range, team, member, and task.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label for="dateFrom" class="mb-1 block text-[11px] font-medium text-gray-500">From</label>
                <input id="dateFrom"
                       type="date"
                       wire:model.live="dateFrom"
                       @if($dateTo) max="{{ $dateTo }}" @endif
                       class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label for="dateTo" class="mb-1 block text-[11px] font-medium text-gray-500">To</label>
                <input id="dateTo"
                       type="date"
                       wire:model.live="dateTo"
                       @if($dateFrom) min="{{ $dateFrom }}" @endif
                       class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label for="teamId" class="mb-1 block text-[11px] font-medium text-gray-500">Team</label>
                <select id="teamId" wire:model.live="teamId" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">All teams</option>
                    @foreach($leadTeams as $team)
                        @php($teamProject = $team->activeProject((int) session('active_project_id', 0)))
                        <option value="{{ $team->id }}">{{ $team->name }}@if($teamProject) / {{ $teamProject->name }}@endif</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="memberId" class="mb-1 block text-[11px] font-medium text-gray-500">Member</label>
                <select id="memberId" wire:model.live="memberId" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">All members</option>
                    @foreach($members as $member)
                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="taskId" class="mb-1 block text-[11px] font-medium text-gray-500">Task</label>
                <select id="taskId" wire:model.live="taskId" class="min-w-[14rem] rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">All tasks</option>
                    @foreach($tasks as $task)
                        <option value="{{ $task->id }}">{{ $task->title }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="ui-soft-panel px-5 py-4">
        <p class="text-sm text-gray-500">Logged time</p>
        <p class="mt-1 text-3xl font-extrabold text-gray-900">{{ intdiv($totalMinutes, 60) }}h {{ $totalMinutes % 60 }}m</p>
    </div>

    <div class="ui-soft-panel relative overflow-hidden">
        <x-loading-skeleton wire:loading.delay class="ui-loading-overlay" wire:target="dateFrom,dateTo,teamId,memberId,taskId" />
        @forelse($logs as $log)
            <article class="border-b border-gray-100 px-5 py-4 last:border-b-0">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $log->task?->title ?? 'General work' }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $log->user?->name ?? 'Deleted user' }}
                            / {{ $log->task?->team?->name ?? $log->team?->name ?? 'No team' }}
                            / {{ $log->task?->project?->name ?? $log->team?->activeProject((int) session('active_project_id', 0))?->name ?? 'No project' }}
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



