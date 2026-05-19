<div class="space-y-6">

    {{-- Flash --}}
    @if(session('success'))
        <x-floating-notification :message="session('success')" />
    @endif

    {{-- ── Role filter tabs + search + new user ─────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        {{-- Role tabs --}}
        <div class="flex border-b border-gray-200">
            @php
                $tabs = [
                    ''          => ['label' => 'All',       'count' => $roleCounts['all']],
                    'admin'     => ['label' => 'Admin',     'count' => $roleCounts['admin']],
                    'team_lead' => ['label' => 'Team Lead', 'count' => $roleCounts['team_lead']],
                    'member'    => ['label' => 'Member',    'count' => $roleCounts['member']],
                    'client'    => ['label' => 'Client',    'count' => $roleCounts['client']],
                ];
            @endphp
            @foreach($tabs as $key => $tab)
                <button wire:click="$set('filterRole', '{{ $key }}')"
                        class="px-4 py-2.5 text-sm font-medium border-b-2 transition -mb-px whitespace-nowrap
                               {{ $filterRole === $key
                                  ? 'border-indigo-600 text-indigo-700'
                                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    {{ $tab['label'] }}
                    <span class="ml-1.5 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-xs
                                 {{ $filterRole === $key ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $tab['count'] }}
                    </span>
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-3">
            <input wire:model.live.debounce.300ms="search"
                   type="text"
                   placeholder="Search name or email…"
                   class="w-56 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">

            @if(!$showForm)
                <button wire:click="openCreate"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New User
                </button>
            @endif
        </div>
    </div>

    {{-- ── Create / Edit form ───────────────────────────────────────────────── --}}
    @if($showForm)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-5">
                {{ $editingId ? 'Edit User' : 'Create New User' }}
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input wire:model="name" type="text" placeholder="Juan dela Cruz"
                           class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
                    @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input wire:model="email" type="email" placeholder="user@example.com"
                           class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('email') border-red-400 @enderror">
                    @error('email') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Role --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Role <span class="text-red-500">*</span>
                    </label>
                    <select wire:model="role"
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('role') border-red-400 @enderror">
                        <option value="member">Member</option>
                        <option value="team_lead">Team Lead</option>
                        <option value="client">Client</option>
                        <option value="admin">Admin</option>
                    </select>
                    @error('role') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Password --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Password {{ $editingId ? '(leave blank to keep current)' : '' }}
                        @if(!$editingId) <span class="text-red-500">*</span> @endif
                    </label>
                    <input wire:model="password" type="password" placeholder="Min. 8 characters"
                           class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('password') border-red-400 @enderror">
                    @error('password') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-3 mt-6">
                <button wire:click="save"
                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    {{ $editingId ? 'Update User' : 'Create User' }}
                </button>
                <button wire:click="cancelForm"
                        class="px-5 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
            </div>
        </div>
    @endif

    {{-- ── User table ───────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" @if(!$showForm) wire:poll.visible.15s @endif>
        @if($users->isEmpty())
            <div class="py-16 text-center text-gray-400">
                <p class="text-sm">No users found.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Teams</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Joined</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($users as $user)
                        @php
                            $roleBadge = match($user->role) {
                                'admin'     => 'bg-purple-100 text-purple-700',
                                'client'    => 'bg-blue-100 text-blue-700',
                                'team_lead' => 'bg-indigo-100 text-indigo-700',
                                'member'    => 'bg-gray-100 text-gray-600',
                                default     => 'bg-gray-100 text-gray-500',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-bold flex-shrink-0">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">
                                            {{ $user->name }}
                                            @if($user->id === auth()->id())
                                                <span class="ml-1 text-xs text-gray-400">(you)</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-400">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Inline role dropdown -------------------------------- --}}
                            <td class="px-6 py-4">
                                <select wire:change="changeRole({{ $user->id }}, $event.target.value)"
                                        class="text-xs font-medium border rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-indigo-500 {{ $roleBadge }}"
                                        {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                                    <option value="member"    {{ $user->role === 'member'    ? 'selected' : '' }}>Member</option>
                                    <option value="team_lead" {{ $user->role === 'team_lead' ? 'selected' : '' }}>Team Lead</option>
                                    <option value="client"    {{ $user->role === 'client'    ? 'selected' : '' }}>Client</option>
                                    <option value="admin"     {{ $user->role === 'admin'     ? 'selected' : '' }}>Admin</option>
                                </select>
                                @if($user->id === auth()->id())
                                    <p class="text-xs text-gray-400 mt-0.5">Your own role</p>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-gray-500">
                                @if($user->role === 'team_lead')
                                    <span title="Teams led">
                                        {{ $user->led_teams_count }} led
                                    </span>
                                @elseif($user->role === 'member')
                                    <span title="Teams joined">
                                        {{ $user->teams_count }} joined
                                    </span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-gray-400 whitespace-nowrap text-xs">
                                {{ $user->created_at?->format('M d, Y') ?? '—' }}
                            </td>

                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <button wire:click="openEdit({{ $user->id }})"
                                        class="text-indigo-600 hover:text-indigo-800 text-xs font-medium mr-3 transition">
                                    Edit
                                </button>
                                @if($user->id !== auth()->id())
                                    <button wire:click="confirmDelete({{ $user->id }})"
                                            class="text-red-500 hover:text-red-700 text-xs font-medium transition">
                                        Delete
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <x-confirmation-modal wire:model="confirmingDelete" maxWidth="md">
        <x-slot name="title">
            Delete user?
        </x-slot>

        <x-slot name="content">
            Delete {{ $deleteName ?: 'this user' }}? This cannot be undone.
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
