<div>
    {{-- Flash message --}}
    @if(session('success'))
        <x-floating-notification :message="session('success')" />
    @endif

    {{-- Header row --}}
    <div class="flex items-center justify-between mb-6">
        <div></div>
        @if(!$showForm)
            <button wire:click="openCreate"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Team
            </button>
        @endif
    </div>

    {{-- Create / Edit form --}}
    @if($showForm)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-800 mb-5">
                {{ $editingId ? 'Edit Team' : 'New Team' }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Team Name <span class="text-red-500">*</span></label>
                    <input wire:model="name" type="text" placeholder="e.g. Frontend Team"
                           class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
                    @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Project --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project <span class="text-red-500">*</span></label>
                    <select wire:model="projectId"
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('projectId') border-red-400 @enderror">
                        <option value="">— Select project —</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                    @error('projectId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Team Lead --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Team Lead <span class="text-red-500">*</span></label>
                    <select wire:model="leadId"
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('leadId') border-red-400 @enderror">
                        <option value="">— Select team lead —</option>
                        @foreach($leads as $lead)
                            <option value="{{ $lead->id }}">{{ $lead->name }}</option>
                        @endforeach
                    </select>
                    @error('leadId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-3 mt-6">
                <button wire:click="save"
                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    {{ $editingId ? 'Update Team' : 'Create Team' }}
                </button>
                <button wire:click="cancelForm"
                        class="px-5 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
            </div>
        </div>
    @endif

    {{-- Teams list --}}
    <div class="space-y-3">
        @forelse($teams as $team)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                {{-- Team row --}}
                <div class="flex items-center gap-4 px-6 py-4">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-900">{{ $team->name }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $team->project->name }}</p>
                    </div>
                    <div class="text-sm text-gray-600 hidden md:block">
                        Lead: <span class="font-medium text-gray-800">{{ $team->lead->name }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        @foreach($team->members->take(4) as $member)
                            <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-semibold"
                                 title="{{ $member->name }}">
                                {{ strtoupper(substr($member->name, 0, 1)) }}
                            </div>
                        @endforeach
                        @if($team->members->count() > 4)
                            <div class="w-7 h-7 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center text-xs font-semibold">
                                +{{ $team->members->count() - 4 }}
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 text-xs font-medium">
                        <button wire:click="openMembers({{ $team->id }})"
                                class="{{ $managingTeamId === $team->id ? 'text-indigo-700 bg-indigo-50' : 'text-indigo-600 hover:text-indigo-800' }} px-3 py-1.5 rounded-lg border border-indigo-200 transition">
                            Members ({{ $team->members->count() }})
                        </button>
                        <button wire:click="openEdit({{ $team->id }})"
                                class="text-gray-600 hover:text-gray-800 px-3 py-1.5 rounded-lg border border-gray-200 transition">
                            Edit
                        </button>
                        <button wire:click="confirmDelete({{ $team->id }})"
                                class="text-red-500 hover:text-red-700 px-3 py-1.5 rounded-lg border border-red-200 transition">
                            Delete
                        </button>
                    </div>
                </div>

                {{-- Member management panel --}}
                @if($managingTeamId === $team->id)
                    <div class="border-t border-gray-100 bg-gray-50 px-6 py-4">
                        <div class="flex items-center gap-3 mb-3">
                            <select wire:model="memberToAdd"
                                    class="flex-1 max-w-xs px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">— Add a member —</option>
                                @foreach($availableUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <button wire:click="addMember"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                Add
                            </button>
                        </div>

                        @if($managingTeam && $managingTeam->members->count())
                            <div class="flex flex-wrap gap-2">
                                @foreach($managingTeam->members as $member)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-white border border-gray-200 rounded-full text-xs font-medium text-gray-700">
                                        {{ $member->name }}
                                        <button wire:click="removeMember({{ $member->id }})"
                                                class="text-gray-400 hover:text-red-500 transition">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-gray-400">No members yet.</p>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-400">
                <p class="text-sm">No teams yet. Create one to get started.</p>
            </div>
        @endforelse
    </div>

    <x-confirmation-modal wire:model="confirmingDelete" maxWidth="md">
        <x-slot name="title">
            Delete team?
        </x-slot>

        <x-slot name="content">
            This will remove the team and all of its tasks.
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="cancelDelete" wire:loading.attr="disabled">
                Cancel
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="deleteConfirmed" wire:loading.attr="disabled">
                Delete
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>
</div>
