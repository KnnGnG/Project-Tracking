<div class="space-y-6" wire:poll.visible.60s>
    @if($flash)
        <x-floating-notification :message="$flash" dismiss="dismissFlash" />
    @endif

    @if($teams->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach($teams as $team)
                @php
                    $activeProjectId = (int) session('active_project_id', 0);
                    $teamProject = $activeProjectId > 0
                        ? $team->assignedProjects()->firstWhere('id', $activeProjectId)
                        : $team->assignedProjects()->first();
                @endphp
                <button wire:click="selectTeam({{ $team->id }})"
                        class="rounded-xl border px-4 py-2 text-sm font-semibold transition
                               {{ $selectedTeamId === $team->id
                                  ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm'
                                  : 'border-gray-200 bg-white text-gray-600 hover:border-indigo-300 hover:text-indigo-700' }}">
                    {{ $team->name }}
                    <span class="ml-1 text-xs opacity-70">{{ $teamProject?->name }}</span>
                </button>
            @endforeach
        </div>
    @endif

    @if(!$selectedTeam)
        <div class="ui-empty-state">
            <p class="text-sm font-semibold text-gray-700">You are not leading any project teams yet.</p>
            <p class="mt-1 text-sm text-gray-500">Evaluations will be available once you lead a team.</p>
        </div>
    @else
        @php
            $formScores = collect([
                'Quality' => $qualityScore,
                'Productivity' => $productivityScore,
                'Teamwork' => $teamworkScore,
                'Communication' => $communicationScore,
                'Reliability' => $reliabilityScore,
            ]);
            $formAverage = round($formScores->avg(), 1);
            $activeProjectId = (int) session('active_project_id', 0);
            $selectedProject = $activeProjectId > 0
                ? $selectedTeam->assignedProjects()->firstWhere('id', $activeProjectId)
                : $selectedTeam->assignedProjects()->first();
            $selectedProjectNames = $selectedProject?->name;
            $latestLeadFeedback = $leadFeedback->first();
            $leadFeedbackAverage = $leadFeedback->isNotEmpty()
                ? round($leadFeedback->avg(fn ($evaluation) => $evaluation->averageScore()), 1)
                : null;
        @endphp

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-6 py-4">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Feedback About You</h2>
                    <p class="mt-0.5 text-xs text-gray-400">Member evaluations of your leadership for {{ $selectedTeam->name }}.</p>
                </div>
                @if($leadFeedbackAverage)
                    <span class="rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700">
                        Average {{ $leadFeedbackAverage }}/5
                    </span>
                @endif
            </div>

            @if($leadFeedback->isEmpty())
                <div class="ui-empty-state rounded-none border-0 shadow-none">
                    Member feedback about your team lead support will appear here.
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 p-6 lg:grid-cols-3">
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Responses</p>
                        <p class="mt-2 text-2xl font-extrabold text-gray-900">{{ $leadFeedback->count() }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $latestLeadFeedback?->created_at?->format('M d, Y') }} latest</p>
                    </div>
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Overall Average</p>
                        <p class="mt-2 text-2xl font-extrabold text-indigo-700">{{ $leadFeedbackAverage }}/5</p>
                        <p class="mt-1 text-sm text-gray-600">Across visible feedback</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Latest Team</p>
                        <p class="mt-2 text-sm font-bold text-gray-900">{{ $latestLeadFeedback?->team?->name ?? $selectedTeam->name }}</p>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ $latestLeadFeedback?->period_start?->format('M d, Y') ?? 'No start' }} - {{ $latestLeadFeedback?->period_end?->format('M d, Y') ?? 'No end' }}
                        </p>
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    @foreach($leadFeedback as $feedback)
                        @php
                            $scores = collect([
                                'Leadership' => $feedback->leadership_score,
                                'Communication' => $feedback->communication_score,
                                'Support' => $feedback->support_score,
                                'Organization' => $feedback->organization_score,
                                'Fairness' => $feedback->fairness_score,
                            ]);
                        @endphp
                        <article class="px-6 py-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-bold text-gray-900">{{ $feedback->evaluator?->name ?? 'Member' }}</h3>
                                    <p class="mt-1 text-xs text-gray-400">
                                        {{ $feedback->period_start?->format('M d, Y') ?? 'No start' }} - {{ $feedback->period_end?->format('M d, Y') ?? 'No end' }}
                                        / Saved {{ $feedback->created_at->format('M d, Y h:i A') }}
                                    </p>
                                </div>
                                <span class="rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">
                                    {{ $feedback->averageScore() }}/5
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                @foreach($scores as $label => $score)
                                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                                        <p class="text-xs font-semibold text-gray-500">{{ $label }}</p>
                                        <p class="mt-1 text-sm font-extrabold text-gray-900">{{ $score }}/5</p>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
                                <section class="rounded-xl border border-gray-100 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Summary</p>
                                    <p class="mt-2 text-sm leading-6 text-gray-700">{{ $feedback->summary ?: 'No summary provided.' }}</p>
                                </section>
                                <section class="rounded-xl border border-green-100 bg-green-50/40 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-green-600">Strengths</p>
                                    <p class="mt-2 text-sm leading-6 text-gray-700">{{ $feedback->strengths ?: 'No strengths noted.' }}</p>
                                </section>
                                <section class="rounded-xl border border-amber-100 bg-amber-50/40 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Areas to Improve</p>
                                    <p class="mt-2 text-sm leading-6 text-gray-700">{{ $feedback->improvements ?: 'No improvement notes provided.' }}</p>
                                </section>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <section class="space-y-4 xl:col-span-1">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">{{ $selectedProjectNames ?: 'Project team' }}</p>
                        <h2 class="mt-1 text-sm font-semibold text-gray-900">Team Members</h2>
                        <p class="mt-0.5 text-xs text-gray-400">Choose a member to evaluate.</p>
                    </div>

                    <div class="divide-y divide-gray-50">
                        @forelse($members as $member)
                            @php
                                $latest = $latestByMember->get($member->id);
                                $avg = $latest?->averageScore();
                            @endphp
                            <button wire:click="selectMember({{ $member->id }})"
                                    class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left transition hover:bg-gray-50
                                           {{ $selectedMemberId === $member->id ? 'bg-indigo-50/70' : '' }}">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700">
                                        {{ strtoupper(substr($member->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $member->name }}</p>
                                        <p class="truncate text-xs text-gray-400">{{ $member->email }}</p>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    @if($avg)
                                        <p class="text-sm font-bold text-indigo-600">{{ $avg }}/5</p>
                                        <p class="text-[11px] text-gray-400">latest</p>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-500">New</span>
                                    @endif
                                </div>
                            </button>
                        @empty
                            <div class="px-5 py-10 text-center text-sm text-gray-400">
                                No regular members in this team yet.
                            </div>
                        @endforelse
                    </div>
                </div>

                @if($selectedMember)
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Current Snapshot</p>
                        <h3 class="mt-1 text-base font-bold text-gray-900">{{ $selectedMember->name }}</h3>
                        <p class="mt-1 text-sm text-gray-500">Use current work signals to make the evaluation specific.</p>
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-lg font-bold text-gray-900">{{ $metrics['tasks'] }}</p>
                                <p class="text-xs text-gray-400">Assigned</p>
                            </div>
                            <div class="rounded-lg bg-green-50 p-3">
                                <p class="text-lg font-bold text-green-700">{{ $metrics['done'] }}</p>
                                <p class="text-xs text-green-600">Completed</p>
                            </div>
                            <div class="rounded-lg bg-amber-50 p-3">
                                <p class="text-lg font-bold text-amber-700">{{ $metrics['review'] }}</p>
                                <p class="text-xs text-amber-600">In Review</p>
                            </div>
                            <div class="rounded-lg bg-indigo-50 p-3">
                                <p class="text-lg font-bold text-indigo-700">
                                    {{ intdiv($metrics['loggedMinutes'], 60) }}h {{ $metrics['loggedMinutes'] % 60 }}m
                                </p>
                                <p class="text-xs text-indigo-600">Logged</p>
                            </div>
                        </div>
                    </div>
                @endif
            </section>

            <section class="space-y-6 xl:col-span-2">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-6 py-4">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">
                                {{ $editingId ? 'Edit Evaluation' : 'New Evaluation' }}
                            </h2>
                            <p class="mt-0.5 text-xs text-gray-400">
                                These exact scores and notes will appear in the member's My Evaluations page.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($selectedMember)
                                <span class="rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700">
                                    Preview average {{ $formAverage }}/5
                                </span>
                            @endif
                            @if($editingId)
                                <button wire:click="cancelEdit" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                                    Cancel edit
                                </button>
                            @endif
                        </div>
                    </div>

                    @if(!$selectedMember)
                        <div class="ui-empty-state rounded-none border-0 shadow-none">
                            Select a team member first.
                        </div>
                    @else
                        <form wire:submit.prevent="save" class="space-y-5 p-6">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="md:col-span-1 rounded-xl border border-gray-100 bg-gray-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Member</p>
                                    <p class="mt-1 text-sm font-bold text-gray-900">{{ $selectedMember->name }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $selectedTeam->name }}</p>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-gray-500">Period Start</label>
                                    <input type="date" wire:model="periodStart" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('periodStart') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-gray-500">Period End</label>
                                    <input type="date" wire:model="periodEnd" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('periodEnd') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                                @foreach([
                                    'qualityScore' => ['label' => 'Quality', 'hint' => 'Output standard'],
                                    'productivityScore' => ['label' => 'Productivity', 'hint' => 'Work completed'],
                                    'teamworkScore' => ['label' => 'Teamwork', 'hint' => 'Collaboration'],
                                    'communicationScore' => ['label' => 'Communication', 'hint' => 'Clarity'],
                                    'reliabilityScore' => ['label' => 'Reliability', 'hint' => 'Follow-through'],
                                ] as $field => $meta)
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                                        <label class="text-xs font-semibold text-gray-500">{{ $meta['label'] }}</label>
                                        <p class="mt-0.5 text-[11px] text-gray-400">{{ $meta['hint'] }}</p>
                                        <select wire:model.live="{{ $field }}" class="mt-2 w-full rounded-lg border-gray-300 bg-white text-sm font-semibold text-gray-800 focus:border-indigo-500 focus:ring-indigo-500">
                                            @for($score = 1; $score <= 5; $score++)
                                                <option value="{{ $score }}">{{ $score }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                @endforeach
                            </div>

                            <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Member-facing score preview</p>
                                        <p class="mt-1 text-sm text-gray-600">The member will see this average plus each category score.</p>
                                    </div>
                                    <p class="text-2xl font-extrabold text-indigo-700">{{ $formAverage }}/5</p>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold text-gray-500">Summary</label>
                                <textarea wire:model="summary" rows="3" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Overall performance, contribution, and current standing."></textarea>
                                @error('summary') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-xs font-semibold text-gray-500">Strengths</label>
                                    <textarea wire:model="strengths" rows="3" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="What the member is doing well."></textarea>
                                    @error('strengths') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-gray-500">Areas to Improve</label>
                                    <textarea wire:model="improvements" rows="3" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Specific next steps or coaching points."></textarea>
                                    @error('improvements') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            @error('selectedMemberId') <p class="text-sm text-red-500">{{ $message }}</p> @enderror

                            <div class="flex justify-end">
                                <button type="submit"
                                        wire:loading.attr="disabled"
                                        wire:target="save"
                                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update Evaluation' : 'Save Evaluation' }}</span>
                                    <span wire:loading wire:target="save">Saving...</span>
                                </button>
                            </div>
                        </form>
                    @endif
                </div>

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-sm font-semibold text-gray-900">Evaluation History</h2>
                        <p class="mt-0.5 text-xs text-gray-400">Detailed records for {{ $selectedTeam->name }}. Members see this same score and note data.</p>
                    </div>

                    <div class="divide-y divide-gray-50">
                        @forelse($evaluations as $evaluation)
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
                                $projectNames = $evaluation->team?->assignedProjects()?->pluck('name')->join(', ') ?: 'No project';
                            @endphp
                            <article class="px-6 py-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-base font-bold text-gray-900">{{ $evaluation->member?->name }}</h3>
                                            <span class="rounded-full border px-2.5 py-1 text-xs font-bold {{ $scoreTone }}">
                                                Overall {{ $evaluation->averageScore() }}/5
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm text-gray-500">
                                            {{ $projectNames }} / Evaluated by {{ $evaluation->evaluator?->name ?? 'Team Lead' }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-400">
                                            {{ $evaluation->period_start?->format('M d, Y') ?? 'No start' }} - {{ $evaluation->period_end?->format('M d, Y') ?? 'No end' }}
                                            / Saved {{ $evaluation->created_at->format('M d, Y h:i A') }}
                                        </p>
                                    </div>
                                    @if($evaluation->evaluator_id === auth()->id())
                                        <div class="flex gap-2">
                                            <button wire:click="editEvaluation({{ $evaluation->id }})" wire:loading.attr="disabled" wire:target="editEvaluation" class="ui-action-button ui-action-primary">
                                                Edit
                                            </button>
                                            <button wire:click="deleteEvaluation({{ $evaluation->id }})" wire:confirm="Delete this evaluation?" wire:loading.attr="disabled" wire:target="deleteEvaluation" class="ui-action-button ui-action-danger">
                                                Delete
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                    @foreach($scores as $label => $score)
                                        @php
                                            $barWidth = max(0, min(100, ($score / 5) * 100));
                                        @endphp
                                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-xs font-semibold text-gray-500">{{ $label }}</p>
                                                <p class="text-sm font-extrabold text-gray-900">{{ $score }}/5</p>
                                            </div>
                                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-white">
                                                <div class="h-full rounded-full bg-indigo-500" style="width: {{ $barWidth }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div class="rounded-xl border border-green-100 bg-green-50/40 p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-green-600">Strongest Area</p>
                                        <p class="mt-1 text-sm font-semibold text-gray-900">{{ $strongestAreas }} ({{ $highestScore }}/5)</p>
                                    </div>
                                    <div class="rounded-xl border border-amber-100 bg-amber-50/40 p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Needs Focus</p>
                                        <p class="mt-1 text-sm font-semibold text-gray-900">{{ $focusAreas }} ({{ $lowestScore }}/5)</p>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
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
                        @empty
                            <div class="ui-empty-state rounded-none border-0 shadow-none">
                                No evaluations recorded for this team yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    @endif
</div>
