<div x-data="{ open: false }" class="relative" wire:poll.visible.60s>
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
            <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white shadow-sm ring-2 ring-white">
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
         style="width: min(26rem, calc(100vw - 2rem));" class="absolute right-0 z-50 mt-2 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
            <div>
                <h2 class="text-sm font-bold text-gray-900">Notifications</h2>
                <p class="mt-0.5 text-xs text-gray-400">
                    {{ $unreadCount }} unread item{{ $unreadCount === 1 ? '' : 's' }}
                </p>
            </div>
            @if($unreadNotificationsCount > 0)
                <button type="button" wire:click="markAllRead" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-50 hover:text-indigo-800">
                    Mark all read
                </button>
            @endif
        </div>

        <div class="max-h-[22rem] overflow-y-auto bg-gray-50/60 p-2">
            @forelse($overdueTasks as $task)
                <a href="{{ auth()->user()->isTeamLead() ? route('lead.tasks') : (auth()->user()->isMember() ? route('member.dashboard', ['tab' => 'exceeded']) : route('dashboard')) }}"
                   class="mb-2 block rounded-lg border border-red-100 bg-white px-3 py-3 shadow-sm transition hover:border-red-200 hover:bg-red-50/60">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-500 ring-1 ring-red-100">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-xs font-bold uppercase tracking-wide text-red-600">Overdue task</p>
                                <span class="shrink-0 rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-600">Due</span>
                            </div>
                            <p class="mt-0.5 truncate text-sm font-semibold text-gray-900">{{ $task->title }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $task->due_date?->format('M d, Y') }}</p>
                        </div>
                    </div>
                </a>
            @empty
            @endforelse

            @forelse($notifications as $notification)
                <div class="mb-2 rounded-lg border border-gray-100 bg-white px-3 py-3 shadow-sm transition {{ $notification->read_at ? '' : 'ring-1 ring-indigo-100' }}">
                    <div class="flex items-start justify-between gap-3">
                        <a href="{{ $notification->url ?: '#' }}"
                           wire:click.prevent="openNotification({{ $notification->id }})"
                           class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                @if(!$notification->read_at)
                                    <span class="h-2 w-2 rounded-full bg-indigo-500"></span>
                                @endif
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $notification->title }}</p>
                            </div>
                            @if($notification->body)
                                <p class="mt-1 line-clamp-2 text-sm leading-5 text-gray-600">{{ $notification->body }}</p>
                            @endif
                            <p class="mt-1.5 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                        </a>

                        @if(!$notification->read_at)
                            <button type="button"
                                    wire:click="markRead({{ $notification->id }})"
                                    class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-50 hover:text-indigo-800">
                                Read
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                @if($overdueTasks->isEmpty())
                    <div class="rounded-lg border border-dashed border-gray-200 bg-white px-4 py-10 text-center">
                        <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-50 text-gray-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/>
                            </svg>
                        </div>
                        <p class="mt-3 text-sm font-semibold text-gray-700">No notifications yet</p>
                        <p class="mt-1 text-xs text-gray-400">You are all caught up.</p>
                    </div>
                @endif
            @endforelse
        </div>
    </div>
</div>