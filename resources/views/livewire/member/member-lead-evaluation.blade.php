<div class="space-y-6">
    @if($flash)
        <x-floating-notification :message="$flash" dismiss="dismissFlash" />
    @endif

    <div class="ui-page-heading">
        <div>
            <h2>Team Lead Evaluation</h2>
            <p>Share structured feedback about your team lead's support and coordination.</p>
        </div>
        <a href="{{ route('member.evaluations', array_filter(['team' => $selectedTeamId ?: null])) }}"
           class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back
        </a>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-6 py-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Evaluate Team Lead</h2>
                <p class="mt-0.5 text-xs text-gray-400">Your feedback is saved for the selected team and period.</p>
            </div>
            @if($selectedLead)
                <span class="rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700">
                    Preview average {{ $formAverage }}/5
                </span>
            @endif
        </div>

        @if($teams->isEmpty())
            <div class="ui-empty-state rounded-none border-0 shadow-none">
                You need to be assigned as a member of a team before evaluating a team lead.
            </div>
        @else
            <form wire:submit.prevent="save" class="space-y-5 p-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-500">Team</label>
                        <select wire:model.live="selectedTeamId" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                        @error('selectedTeamId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
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

                <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Selected Lead</p>
                    <p class="mt-1 text-sm font-bold text-gray-900">{{ $selectedLead?->name ?? 'No lead assigned' }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ $selectedTeam?->name ?? 'Choose a team' }}</p>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                    @foreach([
                        'leadershipScore' => ['label' => 'Leadership', 'hint' => 'Direction'],
                        'communicationScore' => ['label' => 'Communication', 'hint' => 'Clarity'],
                        'supportScore' => ['label' => 'Support', 'hint' => 'Helpfulness'],
                        'organizationScore' => ['label' => 'Organization', 'hint' => 'Planning'],
                        'fairnessScore' => ['label' => 'Fairness', 'hint' => 'Consistency'],
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

                <div>
                    <label class="text-xs font-semibold text-gray-500">Summary</label>
                    <textarea wire:model="summary" rows="3" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Overall feedback about leadership, support, and coordination."></textarea>
                    @error('summary') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold text-gray-500">Strengths</label>
                        <textarea wire:model="strengths" rows="3" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="What your team lead does well."></textarea>
                        @error('strengths') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500">Areas to Improve</label>
                        <textarea wire:model="improvements" rows="3" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Specific improvements that would help the team."></textarea>
                        @error('improvements') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="save">Save Team Lead Evaluation</span>
                        <span wire:loading wire:target="save">Saving...</span>
                    </button>
                </div>
            </form>
        @endif
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Your Team Lead Feedback</h2>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($evaluations as $evaluation)
                <div class="px-6 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold text-gray-900">{{ $evaluation->lead?->name ?? 'Team Lead' }}</p>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $evaluation->team?->name ?? 'Team' }} / {{ $evaluation->created_at->format('M d, Y') }}</p>
                        </div>
                        <span class="rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">
                            {{ $evaluation->averageScore() }}/5
                        </span>
                    </div>
                    @if($evaluation->summary)
                        <p class="mt-3 text-sm leading-6 text-gray-700">{{ $evaluation->summary }}</p>
                    @endif
                </div>
            @empty
                <div class="ui-empty-state rounded-none border-0 shadow-none">
                    Saved team lead evaluations will appear here.
                </div>
            @endforelse
        </div>
    </div>
</div>
