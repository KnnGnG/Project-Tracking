<div class="space-y-6" wire:poll.visible.15s>

    @if($flash)
        <x-floating-notification :message="$flash" dismiss="dismissFlash" />
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <section class="xl:col-span-2 bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="px-6 py-5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Add journal log</h2>
                    <p class="text-sm text-gray-500">Record what you worked on and how long it took.</p>
                </div>

                <div class="flex items-center gap-2">
                    <label for="logDate" class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Day</label>
                    <input id="logDate"
                           type="date"
                           wire:model.live="logDate"
                           class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <form wire:submit="save" class="px-6 py-5 space-y-5">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div>
                        <label for="selectedTaskId" class="block text-sm font-medium text-gray-700">Task</label>
                        <select id="selectedTaskId"
                                wire:model="selectedTaskId"
                                class="mt-1 w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">General work / no task</option>
                            @foreach($tasks as $task)
                                <option value="{{ $task->id }}">
                                    {{ $task->title }}@if($task->project) - {{ $task->project->name }}@endif
                                </option>
                            @endforeach
                        </select>
                        @error('selectedTaskId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="hours" class="block text-sm font-medium text-gray-700">Hours</label>
                            <input id="hours"
                                   type="number"
                                   min="0"
                                   max="24"
                                   wire:model="hours"
                                   class="mt-1 w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('hours') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="minutes" class="block text-sm font-medium text-gray-700">Minutes</label>
                            <input id="minutes"
                                   type="number"
                                   min="0"
                                   max="59"
                                   wire:model="minutes"
                                   class="mt-1 w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('minutes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div x-data="journalTimer()" x-init="init()" :class="breakMode ? 'border-sky-200 bg-sky-50' : 'border-indigo-100 bg-indigo-50'" class="rounded-lg border px-4 py-4 transition-colors">
                    <div x-show="breakMode"
                         x-transition.opacity
                         class="fixed inset-0 z-50 flex items-center justify-center bg-sky-950/30 px-4 backdrop-blur-sm">
                        <div class="w-full max-w-md rounded-lg bg-white px-6 py-6 text-center shadow-2xl">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-sky-100 text-sky-700">
                                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900">Time's up! Take a break.</h3>
                            <p class="mt-2 text-sm leading-6 text-gray-600">
                                Step away for a moment, stretch, and let your eyes rest before starting another session.
                            </p>
                            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
                                <button type="button"
                                        @click="saveSession"
                                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-sky-600 text-white hover:bg-sky-700">
                                    Add session to log
                                </button>
                                <button type="button"
                                        @click="dismissBreak"
                                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">
                                    Dismiss
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p :class="breakMode ? 'text-sky-700' : 'text-indigo-700'" class="text-xs font-semibold uppercase tracking-wide">Focus timer</p>
                            <p class="text-3xl font-extrabold tabular-nums text-gray-900" x-text="display">00:00:00</p>
                            <p class="mt-1 text-xs text-gray-500" x-text="running ? 'Work session running' : (breakMode ? 'Break mode active' : 'Ready when you are')"></p>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <button type="button"
                                    x-show="notificationsSupported && notificationPermission !== 'granted'"
                                    @click="requestNotifications"
                                    class="px-3 py-2 rounded-lg text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">
                                Allow alerts
                            </button>
                            <button type="button"
                                    x-show="!running"
                                    @click="start"
                                    :disabled="inputDuration() < 1"
                                    :class="breakMode ? 'bg-sky-600 hover:bg-sky-700' : 'bg-indigo-600 hover:bg-indigo-700'"
                                    class="px-3 py-2 rounded-lg text-sm font-semibold text-white">
                                Start
                            </button>
                            <button type="button"
                                    x-show="running"
                                    @click="pause"
                                    class="px-3 py-2 rounded-lg text-sm font-semibold bg-amber-500 text-white hover:bg-amber-600">
                                Pause
                            </button>
                            <button type="button"
                                    @click="reset"
                                    class="px-3 py-2 rounded-lg text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">
                                Reset
                            </button>
                            <button type="button"
                                    @click="addToForm"
                                    :disabled="elapsed < 1"
                                    class="px-3 py-2 rounded-lg text-sm font-semibold bg-gray-900 text-white hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed">
                                Add time
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Journal notes</label>
                    <textarea id="notes"
                              wire:model="notes"
                              rows="5"
                              placeholder="What did you do today?"
                              class="mt-1 w-full border border-gray-300 rounded-lg text-sm px-3 py-3 leading-6 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-semibold bg-indigo-600 text-white hover:bg-indigo-700">
                        Save log
                    </button>
                </div>
            </form>
        </section>

        <aside class="bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-500">Total for {{ \Illuminate\Support\Carbon::parse($logDate)->format('M d, Y') }}</p>
                <p class="mt-1 text-3xl font-extrabold text-gray-900">
                    {{ intdiv($dailyMinutes, 60) }}h {{ $dailyMinutes % 60 }}m
                </p>
            </div>

            <div class="p-5 space-y-3">
                <h3 class="text-sm font-semibold text-gray-900">Selected day</h3>

                @forelse($logs as $log)
                    <article class="rounded-lg border border-gray-200 px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ intdiv($log->minutes, 60) }}h {{ $log->minutes % 60 }}m
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $log->task?->title ?? 'General work' }}
                                    @if($log->task?->project)
                                        <span class="text-gray-300">/</span> {{ $log->task->project->name }}
                                    @endif
                                </p>
                            </div>
                            <button type="button"
                                    wire:click="confirmDelete({{ $log->id }})"
                                    class="text-xs font-medium text-red-500 hover:text-red-700">
                                Delete
                            </button>
                        </div>
                        <div class="mt-3 rounded-lg bg-gray-50 px-3 py-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Journal notes</p>
                            @if($log->notes)
                                <p class="mt-1 text-sm leading-6 text-gray-700 whitespace-pre-line">{{ $log->notes }}</p>
                            @else
                                <p class="mt-1 text-sm text-gray-400 italic">No notes added for this log.</p>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-200 py-10 text-center text-sm text-gray-400">
                        No logs for this day yet.
                    </div>
                @endforelse
            </div>
        </aside>
    </div>

    <section class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Recent journal logs</h2>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse($recentLogs as $log)
                <div class="px-6 py-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">
                            {{ $log->log_date->format('M d, Y') }} - {{ $log->task?->title ?? 'General work' }}
                        </p>
                        @if($log->notes)
                            <p class="mt-1 text-sm text-gray-600 line-clamp-2">{{ $log->notes }}</p>
                        @endif
                    </div>
                    <span class="text-sm font-semibold text-gray-700">
                        {{ intdiv($log->minutes, 60) }}h {{ $log->minutes % 60 }}m
                    </span>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-gray-400">
                    Older logs will show here after you add entries on other days.
                </div>
            @endforelse
        </div>
    </section>

    <script>
        function journalTimer() {
            return {
                duration: 0,
                remaining: 0,
                elapsed: 0,
                running: false,
                breakMode: false,
                handle: null,
                originalTitle: '',
                originalFavicon: '',
                faviconHandle: null,
                notificationsSupported: false,
                notificationPermission: 'default',
                get display() {
                    const hours = String(Math.floor(this.remaining / 3600)).padStart(2, '0');
                    const minutes = String(Math.floor((this.remaining % 3600) / 60)).padStart(2, '0');
                    const seconds = String(this.remaining % 60).padStart(2, '0');

                    return `${hours}:${minutes}:${seconds}`;
                },
                init() {
                    this.originalTitle = document.title;
                    this.originalFavicon = this.favicon()?.href || '';
                    this.notificationsSupported = 'Notification' in window;
                    this.notificationPermission = this.notificationsSupported ? Notification.permission : 'denied';
                    this.syncFromInputs();
                },
                inputDuration() {
                    return ((Number(this.$wire.hours) || 0) * 3600) + ((Number(this.$wire.minutes) || 0) * 60);
                },
                syncFromInputs() {
                    if (this.running) {
                        return;
                    }

                    this.duration = this.inputDuration();
                    this.remaining = this.duration;
                    this.elapsed = 0;
                },
                requestNotifications() {
                    if (!this.notificationsSupported) {
                        return;
                    }

                    Notification.requestPermission().then((permission) => {
                        this.notificationPermission = permission;
                    });
                },
                start() {
                    if (this.running) {
                        return;
                    }

                    this.syncFromInputs();

                    if (this.duration < 1) {
                        return;
                    }

                    this.dismissBreak();
                    this.running = true;
                    this.handle = setInterval(() => {
                        this.remaining = Math.max(0, this.remaining - 1);
                        this.elapsed = this.duration - this.remaining;

                        if (this.remaining === 0) {
                            this.complete();
                        }
                    }, 1000);
                },
                pause() {
                    this.running = false;
                    clearInterval(this.handle);
                },
                reset() {
                    this.pause();
                    this.syncFromInputs();
                    this.dismissBreak();
                },
                addToForm() {
                    if (this.elapsed < 1) {
                        return;
                    }

                    this.$wire.addTimerMinutes(this.elapsed);
                    this.reset();
                },
                saveSession() {
                    if (this.elapsed < 1) {
                        return;
                    }

                    this.$wire.saveTimerSession(this.elapsed);
                    this.reset();
                },
                complete() {
                    this.pause();
                    this.remaining = 0;
                    this.elapsed = this.duration;
                    this.breakMode = true;
                    document.body.classList.add('break-mode');
                    document.title = 'Break Time!';
                    this.startFaviconAlert();
                    this.sendNotification();
                },
                dismissBreak() {
                    this.breakMode = false;
                    document.body.classList.remove('break-mode');
                    document.title = this.originalTitle || document.title;
                    this.stopFaviconAlert();
                },
                sendNotification() {
                    if (!this.notificationsSupported || this.notificationPermission !== 'granted') {
                        return;
                    }

                    new Notification('Time is up', {
                        body: 'Take a break before starting another work session.',
                        tag: 'journal-focus-timer',
                    });
                },
                favicon() {
                    return document.querySelector("link[rel~='icon']");
                },
                setFavicon(color, text) {
                    let favicon = this.favicon();

                    if (!favicon) {
                        favicon = document.createElement('link');
                        favicon.rel = 'icon';
                        document.head.appendChild(favicon);
                    }

                    const svg = `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='14' fill='${color}'/><text x='32' y='42' font-size='34' text-anchor='middle' fill='white' font-family='Arial, sans-serif'>${text}</text></svg>`;
                    favicon.href = `data:image/svg+xml,${encodeURIComponent(svg)}`;
                },
                startFaviconAlert() {
                    this.stopFaviconAlert(false);

                    let active = false;
                    this.faviconHandle = setInterval(() => {
                        active = !active;
                        this.setFavicon(active ? '#38bdf8' : '#2563eb', active ? 'B' : '!');
                    }, 700);
                },
                stopFaviconAlert(restore = true) {
                    clearInterval(this.faviconHandle);
                    this.faviconHandle = null;

                    if (!restore || !this.originalFavicon) {
                        return;
                    }

                    const favicon = this.favicon();
                    if (favicon) {
                        favicon.href = this.originalFavicon;
                    }
                },
            };
        }
    </script>

    <style>
        body.break-mode main {
            background: #eff6ff;
            transition: background-color 300ms ease;
        }

        body.break-mode header {
            background: #e0f2fe;
            transition: background-color 300ms ease;
        }
    </style>

    <x-confirmation-modal wire:model="confirmingDelete" maxWidth="md">
        <x-slot name="title">
            Delete journal log?
        </x-slot>

        <x-slot name="content">
            This journal entry will be permanently removed from the selected day.
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
