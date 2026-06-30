<div class="space-y-6" wire:poll.visible.60s>
    @if($flash)
        <x-floating-notification :message="$flash" dismiss="dismissFlash" />
    @endif

    @if($teams->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach($teams as $team)
                <button wire:click="selectTeam({{ $team->id }})"
                        class="rounded-xl border px-4 py-2 text-sm font-semibold transition
                               {{ $selectedTeamId === $team->id
                                  ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm'
                                  : 'border-gray-200 bg-white text-gray-600 hover:border-indigo-300 hover:text-indigo-700' }}">
                    {{ $team->name }}
                    <span class="ml-1 text-xs opacity-70">{{ $team->project?->name }}</span>
                </button>
            @endforeach
        </div>
    @endif

    @if(!$selectedTeam)
        <div class="rounded-xl border border-gray-200 bg-white py-20 text-center shadow-sm">
            <p class="text-sm text-gray-400">You are not leading any project teams yet.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <section class="space-y-4 xl:col-span-1">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h2 class="text-sm font-semibold text-gray-900">Team Members</h2>
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
                                Score consistently from 1 to 5, then add useful coaching notes.
                            </p>
                        </div>
                        @if($editingId)
                            <button wire:click="cancelEdit" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                                Cancel edit
                            </button>
                        @endif
                    </div>

                    @if(!$selectedMember)
                        <div class="px-6 py-16 text-center text-sm text-gray-400">
                            Select a team member first.
                        </div>
                    @else
                        <form wire:submit.prevent="save" class="space-y-5 p-6">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                                    'qualityScore' => 'Quality',
                                    'productivityScore' => 'Productivity',
                                    'teamworkScore' => 'Teamwork',
                                    'communicationScore' => 'Communication',
                                    'reliabilityScore' => 'Reliability',
                                ] as $field => $label)
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                                        <label class="text-xs font-semibold text-gray-500">{{ $label }}</label>
                                        <select wire:model="{{ $field }}" class="mt-2 w-full rounded-lg border-gray-300 bg-white text-sm font-semibold text-gray-800 focus:border-indigo-500 focus:ring-indigo-500">
                                            @for($score = 1; $score <= 5; $score++)
                                                <option value="{{ $score }}">{{ $score }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                @endforeach
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
                                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                                    {{ $editingId ? 'Update Evaluation' : 'Save Evaluation' }}
                                </button>
                            </div>
                        </form>
                    @endif
                </div>

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-sm font-semibold text-gray-900">Evaluation History</h2>
                        <p class="mt-0.5 text-xs text-gray-400">Recent evaluations for {{ $selectedTeam->name }}.</p>
                    </div>

                    <div class="divide-y divide-gray-50">
                        @forelse($evaluations as $evaluation)
                            <article class="px-6 py-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-sm font-semibold text-gray-900">{{ $evaluation->member?->name }}</h3>
                                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700">
                                                {{ $evaluation->averageScore() }}/5
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-400">
                                            {{ $evaluation->period_start?->format('M d, Y') ?? 'No start' }} - {{ $evaluation->period_end?->format('M d, Y') ?? 'No end' }}
                                            / {{ $evaluation->created_at->format('M d, Y') }}
                                        </p>
                                    </div>
                                    @if($evaluation->evaluator_id === auth()->id())
                                      <div class="flex gap-2">
                                        <button wire:click="editEvaluation({{ $evaluation->id }})" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                                            Edit
                                        </button>
                                        <button wire:click="deleteEvaluation({{ $evaluation->id }})" wire:confirm="Delete this evaluation?" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">
                                            Delete
                                        </button>
                                    </div>
                                </div>

                                @if($evaluation->summary)
                                    <p class="mt-3 text-sm leading-6 text-gray-600">{{ $evaluation->summary }}</p>
                                @endif
                            </article>
                        @empty
                            <div class="px-6 py-12 text-center text-sm text-gray-400">
                                No evaluations recorded for this team yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    @endif
</div>
