<div class="space-y-5">
    @if(session('success'))
        <x-floating-notification :message="session('success')" />
    @endif

    <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-900">Premade Teams</p>
            <p class="mt-0.5 text-xs text-gray-400">Build reusable teams before assigning them to projects.</p>
        </div>
        @if(!$showForm)
            <button wire:click="openCreate"
                    wire:loading.attr="disabled"
                    wire:target="openCreate"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Premade Team
            </button>
        @endif
    </div>

    @if($showForm)
        <div class="ui-soft-panel p-5">
            <h3 class="mb-4 text-base font-semibold text-gray-800">
                {{ $editingId ? 'Edit Team' : 'Add Premade Team' }}
            </h3>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Team Name <span class="text-red-500">*</span></label>
                    <input wire:model="name"
                           type="text"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                           placeholder="e.g. Frontend Core Team">
                    @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Team Lead</label>
                    <select wire:model="leadId"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">No team lead yet</option>
                        @foreach($leads as $lead)
                            <option value="{{ $lead->id }}">{{ $lead->name }}</option>
                        @endforeach
                    </select>
                    @error('leadId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Project</label>
                    <select wire:model="projectId"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">No project yet</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Leave blank to keep this as a premade team.</p>
                    @error('projectId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div class="lg:col-span-3">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Team Members</label>
                    <div class="rounded-lg border border-gray-300 bg-white px-3 py-2">
                        @if($members->isEmpty())
                            <p class="py-5 text-center text-sm text-gray-400">No available members yet.</p>
                        @else
                            <div class="grid max-h-72 grid-cols-1 gap-2 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach($members as $member)
                                    @php $isSelectedMember = in_array((string) $member->id, array_map('strval', $memberIds), true); @endphp
                                    <div class="rounded-md px-2 py-2 text-sm transition hover:bg-indigo-50">
                                        <label class="flex cursor-pointer items-center gap-3">
                                            <input type="checkbox"
                                                   wire:model.live="memberIds"
                                                   value="{{ $member->id }}"
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                                                {{ strtoupper(substr($member->name, 0, 1)) }}
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate font-medium text-gray-800">{{ $member->name }}</span>
                                            </span>
                                        </label>
                                        @if($isSelectedMember)
                                            <textarea wire:model="memberNotes.{{ $member->id }}"
                                                      rows="2"
                                                      placeholder="Additional notes for this member..."
                                                      class="mt-2 w-full resize-none rounded-lg border border-gray-200 px-2.5 py-2 text-xs text-gray-600 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                            @error('memberNotes.'.$member->id) <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @error('memberIds') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    @error('memberIds.*') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-5 flex items-center gap-3">
                <button wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update Team' : 'Add Premade Team' }}</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
                <button wire:click="cancelForm"
                        wire:loading.attr="disabled"
                        wire:target="cancelForm,save"
                        class="rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 disabled:opacity-60 disabled:cursor-not-allowed">
                    Cancel
                </button>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2" @if(!$showForm) wire:poll.visible.60s @endif>
        @forelse($teams as $team)
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="truncate text-base font-semibold text-gray-900">{{ $team->name }}</h3>
                            @if($team->project)
                                <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">
                                    Assigned
                                </span>
                            @else
                                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                    Premade
                                </span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ $team->project?->name ?? 'No project assigned yet' }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            Lead: {{ $team->lead?->name ?? 'Unassigned' }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2 text-xs font-semibold">
                        <button wire:click="openEdit({{ $team->id }})"
                                class="rounded-lg border border-gray-200 px-3 py-1.5 text-gray-600 transition hover:bg-gray-50">
                            Edit
                        </button>
                        <button wire:click="confirmDelete({{ $team->id }})"
                                class="rounded-lg border border-red-200 px-3 py-1.5 text-red-600 transition hover:bg-red-50">
                            Delete
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                        {{ $team->regularMembers->count() }} member{{ $team->regularMembers->count() === 1 ? '' : 's' }}
                    </span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                        {{ $team->tasks_count }} task{{ $team->tasks_count === 1 ? '' : 's' }}
                    </span>
                </div>

                @if($team->regularMembers->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($team->regularMembers->take(8) as $member)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700"
                                  title="{{ $member->pivot?->notes ?: $member->name }}">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold">
                                    {{ strtoupper(substr($member->name, 0, 1)) }}
                                </span>
                                {{ $member->name }}
                            </span>
                        @endforeach
                        @if($team->regularMembers->count() > 8)
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500">
                                +{{ $team->regularMembers->count() - 8 }} more
                            </span>
                        @endif
                    </div>

                    @php $membersWithNotes = $team->regularMembers->filter(fn ($member) => filled($member->pivot?->notes))->take(3); @endphp
                    @if($membersWithNotes->isNotEmpty())
                        <div class="mt-3 space-y-1 rounded-lg bg-gray-50 px-3 py-2">
                            @foreach($membersWithNotes as $member)
                                <p class="text-xs leading-5 text-gray-500">
                                    <span class="font-semibold text-gray-700">{{ $member->name }}:</span>
                                    {{ $member->pivot->notes }}
                                </p>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        @empty
            <div class="xl:col-span-2 ui-empty-state">
                <h3 class="text-base font-semibold text-gray-900">No teams yet</h3>
                <p class="mt-2 text-sm text-gray-500">Create a premade team to reuse it in a project later.</p>
            </div>
        @endforelse
    </div>

    @if($confirmingDelete)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">Delete team?</h3>
                <p class="mt-2 text-sm text-gray-500">
                    This will remove the team and its memberships. Existing team tasks will also be removed by the database cascade.
                </p>
                <div class="mt-5 flex justify-end gap-3">
                    <button wire:click="cancelDelete"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button wire:click="deleteConfirmed"
                            wire:loading.attr="disabled"
                            wire:target="deleteConfirmed"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="deleteConfirmed">Delete</span>
                        <span wire:loading wire:target="deleteConfirmed">Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
