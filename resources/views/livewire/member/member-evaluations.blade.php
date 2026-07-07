<div class="space-y-6" wire:poll.visible.60s>
    <div class="ui-page-heading">
        <div>
            <h2>My Evaluations</h2>
            <p>Review the scores, notes, and coaching feedback saved by your team leads.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Evaluations</p>
            <p class="mt-2 text-3xl font-extrabold text-gray-900">{{ $summary['count'] }}</p>
            <p class="mt-1 text-sm text-gray-500">Total feedback records</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Average Score</p>
            <p class="mt-2 text-3xl font-extrabold text-indigo-600">
                {{ $summary['average'] ? $summary['average'].'/5' : '-' }}
            </p>
            <p class="mt-1 text-sm text-gray-500">Across visible evaluations</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Latest Feedback</p>
            <p class="mt-2 text-lg font-bold text-gray-900">
                {{ $summary['latest']?->created_at?->format('M d, Y') ?? 'No feedback yet' }}
            </p>
            <p class="mt-1 text-sm text-gray-500">
                {{ $summary['latest']?->team?->name ?? 'Evaluations will appear here once saved.' }}
            </p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Evaluation History</h2>
                <p class="mt-0.5 text-xs text-gray-400">These records come directly from the team lead evaluation page.</p>
            </div>
            @if($teams->isNotEmpty())
                <div class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-gray-500">Team</label>
                    <select wire:model.live="filterTeam" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="0">All teams</option>
                        @foreach($teams as $team)
                            @php
                                $activeProjectId = (int) session('active_project_id', 0);
                                $teamProject = $activeProjectId > 0
                                    ? $team->assignedProjects()->firstWhere('id', $activeProjectId)
                                    : $team->assignedProjects()->first();
                            @endphp
                            <option value="{{ $team->id }}">
                                {{ $team->name }}@if($teamProject) / {{ $teamProject->name }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
    </div>

    @if($evaluations->isEmpty())
        <div class="ui-empty-state">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.161c.969 0 1.371 1.24.588 1.81l-3.366 2.445a1 1 0 00-.364 1.118l1.286 3.957c.3.921-.755 1.688-1.539 1.118l-3.366-2.445a1 1 0 00-1.176 0l-3.366 2.445c-.784.57-1.838-.197-1.539-1.118l1.286-3.957a1 1 0 00-.364-1.118L4.062 9.384c-.783-.57-.38-1.81.588-1.81h4.161a1 1 0 00.95-.69l1.288-3.957z" />
                </svg>
            </div>
            <h3 class="mt-4 text-base font-semibold text-gray-900">No evaluations yet</h3>
            <p class="mt-2 text-sm text-gray-500">Your evaluations will appear here after your team lead saves them.</p>
        </div>
    @else
        <div class="space-y-5">
            @foreach($evaluations as $evaluation)
                @php
                    $scores = collect([
                        'Quality' => $evaluation->quality_score,
                        'Productivity' => $evaluation->productivity_score,
                        'Teamwork' => $evaluation->teamwork_score,
                        'Communication' => $evaluation->communication_score,
                        'Reliability' => $evaluation->reliability_score,
                    ]);
                    $highestScore = $scores->max();
                    $lowestScore = $scores->min();
                    $strongestAreas = $scores->filter(fn ($score) => $score === $highestScore)->keys()->join(', ');
                    $focusAreas = $scores->filter(fn ($score) => $score === $lowestScore)->keys()->join(', ');
                    $scoreTone = match (true) {
                        $evaluation->averageScore() >= 4.5 => 'bg-green-50 text-green-700 border-green-100',
                        $evaluation->averageScore() >= 3.5 => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                        $evaluation->averageScore() >= 2.5 => 'bg-amber-50 text-amber-700 border-amber-100',
                        default => 'bg-red-50 text-red-700 border-red-100',
                    };
                    $activeProjectId = (int) session('active_project_id', 0);
                    $evaluationProject = $activeProjectId > 0
                        ? $evaluation->team?->assignedProjects()?->firstWhere('id', $activeProjectId)
                        : $evaluation->team?->assignedProjects()?->first();
                    $projectNames = $evaluationProject?->name ?? $evaluation->team?->project?->name ?? 'No project';
                @endphp

                <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-bold text-gray-900">{{ $evaluation->team?->name ?? 'Team evaluation' }}</h3>
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-bold {{ $scoreTone }}">
                                        Overall {{ $evaluation->averageScore() }}/5
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ $projectNames }} / Evaluated by {{ $evaluation->evaluator?->name ?? 'Team Lead' }}
                                </p>
                            </div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 text-right">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Evaluation Period</p>
                                <p class="mt-1 text-sm font-semibold text-gray-800">
                                    {{ $evaluation->period_start?->format('M d, Y') ?? 'No start' }} - {{ $evaluation->period_end?->format('M d, Y') ?? 'No end' }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-400">Saved {{ $evaluation->created_at->format('M d, Y h:i A') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 px-6 py-5 lg:grid-cols-5">
                        @foreach($scores as $label => $score)
                            @php
                                $barWidth = max(0, min(100, ($score / 5) * 100));
                            @endphp
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold text-gray-500">{{ $label }}</p>
                                    <p class="text-sm font-extrabold text-gray-900">{{ $score }}/5</p>
                                </div>
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-white">
                                    <div class="h-full rounded-full bg-indigo-500" style="width: {{ $barWidth }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-1 gap-4 border-y border-gray-100 bg-gray-50/60 px-6 py-5 md:grid-cols-2">
                        <div class="rounded-xl border border-green-100 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-green-600">Strongest Area</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900">{{ $strongestAreas }} ({{ $highestScore }}/5)</p>
                        </div>
                        <div class="rounded-xl border border-amber-100 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Needs Focus</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900">{{ $focusAreas }} ({{ $lowestScore }}/5)</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 px-6 py-6 lg:grid-cols-3">
                        <section class="rounded-xl border border-gray-100 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Team Lead Summary</p>
                            <p class="mt-2 text-sm leading-6 text-gray-700">{{ $evaluation->summary ?: 'No summary provided.' }}</p>
                        </section>
                        <section class="rounded-xl border border-green-100 bg-green-50/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-green-600">Strengths</p>
                            <p class="mt-2 text-sm leading-6 text-gray-700">{{ $evaluation->strengths ?: 'No strengths noted.' }}</p>
                        </section>
                        <section class="rounded-xl border border-amber-100 bg-amber-50/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Areas to Improve</p>
                            <p class="mt-2 text-sm leading-6 text-gray-700">{{ $evaluation->improvements ?: 'No improvement notes provided.' }}</p>
                        </section>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>

