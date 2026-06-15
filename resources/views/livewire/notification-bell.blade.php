<div x-data="{ open: false }" class="relative" wire:poll.visible.20s>
    <button type="button"
            @click="open = ! open"
            :aria-expanded="open.toString()"
            aria-controls="notifications-panel"
            class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 hover:text-gray-700"
            aria-label="Notifications">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/>
        </svg>

        @if($unreadCount > 0)
            <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-cloak
         id="notifications-panel"
         x-show="open"
         :aria-hidden="(! open).toString()"
         @click.outside="open = false"
         x-transition
         class="absolute right-0 z-50 mt-2 w-96 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Notifications</h2>
            @if($unreadNotificationsCount > 0)
                <button type="button" wire:click="markAllRead" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                    Mark all read
                </button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse($overdueTasks as $task)
                <a href="{{ auth()->user()->isTeamLead() ? route('lead.tasks') : (auth()->user()->isMember() ? route('member.dashboard', ['tab' => 'exceeded']) : route('dashboard')) }}"
                   class="block border-b border-gray-100 bg-red-50 px-4 py-3 transition hover:bg-red-100">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-red-800">Overdue task</p>
                            <p class="truncate text-sm text-red-700">{{ $task->title }}</p>
                            <p class="mt-0.5 text-xs text-red-500">Due {{ $task->due_date?->format('M d, Y') }}</p>
                        </div>
                    </div>
                </a>
            @empty
            @endforelse

            @forelse($notifications as $notification)
                <div class="border-b border-gray-100 px-4 py-3 {{ $notification->read_at ? 'bg-white' : 'bg-indigo-50' }}">
                    <div class="flex items-start justify-between gap-3">
                        <a href="{{ $notification->url ?: '#' }}" class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900">{{ $notification->title }}</p>
                            @if($notification->body)
                                <p class="mt-0.5 text-sm text-gray-600">{{ $notification->body }}</p>
                            @endif
                            <p class="mt-1 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                        </a>

                        @if(!$notification->read_at)
                            <button type="button"
                                    wire:click="markRead({{ $notification->id }})"
                                    class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                Read
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                @if($overdueTasks->isEmpty())
                    <div class="px-4 py-10 text-center text-sm text-gray-400">
                        No notifications yet.
                    </div>
                @endif
            @endforelse
        </div>
    </div>
</div>
