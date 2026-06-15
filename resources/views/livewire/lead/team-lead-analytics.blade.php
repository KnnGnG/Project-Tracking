<div class="space-y-6" wire:key="lead-analytics-{{ $selectedTeamId ?? 'none' }}">

    @if($teams->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 py-20 text-center text-gray-400">
            <p class="text-sm">You are not leading any teams yet.</p>
        </div>
    @else

        {{-- Team selector --}}
        <div class="flex gap-2 flex-wrap">
            @foreach($teams as $team)
                <button wire:click="selectTeam({{ $team->id }})"
                        type="button"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition border
                               {{ $selectedTeamId === $team->id
                                  ? 'bg-indigo-600 text-white border-indigo-600'
                                  : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-400 hover:text-indigo-600' }}">
                    {{ $team->name }}
                    <span class="ml-1.5 text-xs opacity-70">{{ $team->project->name }}</span>
                </button>
            @endforeach
        </div>

        @if(!$selectedTeam)
            <div class="bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-400">
                <p class="text-sm">Choose a team to view charts.</p>
            </div>
        @elseif($selectedTeam->tasks->isEmpty())
            <div class="bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-400">
                <p class="text-sm">No tasks yet for this team — analytics will populate once tasks exist.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                {{-- Completed tasks --}}
                <div class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Completed Tasks Over Time</h2>
                            <p class="mt-1 text-xs text-gray-500">Counts tasks completed each day using the task completion date.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select wire:model.live="completionDays"
                                    class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-600 focus:border-indigo-400 focus:ring-indigo-400">
                                <option value="7">Last 7 days</option>
                                <option value="14">Last 14 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="60">Last 60 days</option>
                            </select>

                            <select wire:model.live="completionMemberId"
                                    class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-600 focus:border-indigo-400 focus:ring-indigo-400">
                                <option value="0">All members</option>
                                @foreach($selectedTeam->members as $member)
                                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                                @endforeach
                            </select>

                            <select wire:model.live="completionPriority"
                                    class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-600 focus:border-indigo-400 focus:ring-indigo-400">
                                <option value="all">All priorities</option>
                                <option value="high">High priority</option>
                                <option value="medium">Medium priority</option>
                                <option value="low">Low priority</option>
                            </select>
                        </div>
                    </div>
                    <div class="relative h-72">
                        <canvas id="chart-completed-lead-{{ $selectedTeamId }}"></canvas>
                    </div>
                </div>

                {{-- Late split --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <div class="mb-3">
                        <h2 class="text-sm font-semibold text-gray-900">On Time vs Late</h2>
                        <p class="mt-1 text-xs text-gray-500">Green is good. Red needs follow-up.</p>
                    </div>
                    <div class="relative max-w-sm mx-auto h-64 flex items-center justify-center">
                        <canvas id="chart-donut-lead-{{ $selectedTeamId }}"></canvas>
                    </div>
                </div>

                {{-- Velocity --}}
                <div class="xl:col-span-3 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Hours Logged by Member</h2>
                            <p class="mt-1 text-xs text-gray-500">
                                Y-axis is total hours logged. General work is included when status is set to all.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select wire:model.live="velocityDays"
                                    class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-600 focus:border-indigo-400 focus:ring-indigo-400">
                                <option value="7">Last 7 days</option>
                                <option value="14">Last 14 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="60">Last 60 days</option>
                            </select>

                            <select wire:model.live="velocityMemberId"
                                    class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-600 focus:border-indigo-400 focus:ring-indigo-400">
                                <option value="0">All members</option>
                                @foreach($selectedTeam->members as $member)
                                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                                @endforeach
                            </select>

                            <select wire:model.live="velocityTaskStatus"
                                    class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-600 focus:border-indigo-400 focus:ring-indigo-400">
                                <option value="all">All task statuses</option>
                                <option value="pending">Pending tasks</option>
                                <option value="in_progress">In progress tasks</option>
                                <option value="review">Review tasks</option>
                                <option value="done">Done tasks</option>
                            </select>
                        </div>
                    </div>
                    <div class="relative h-64">
                        @if($velocity)
                            <canvas id="chart-velocity-lead-{{ $selectedTeamId }}"></canvas>
                        @else
                            <div class="flex h-full items-center justify-center rounded-lg bg-gray-50 text-sm text-gray-400">
                                No journal hours logged for these filters yet.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <script type="application/json" id="lead-analytics-chart-data-{{ $selectedTeamId }}">
                {!! json_encode([
                    'completed' => $completedTasks,
                    'donut' => $punctuality,
                    'velocity' => $velocity,
                ]) !!}
            </script>
        @endif
    @endif
</div>

@once
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script>
(function () {
    let charts = [];

    function destroyCharts() {
        charts.forEach(function (c) { try { c.destroy(); } catch (e) {} });
        charts = [];
    }

    function palette() {
        return {
            teal: '#0d9488',
            violet: '#7c3aed',
            amber: '#d97706',
            slate400: '#94a3b8',
            blue: '#2563eb',
            rose: '#e11d48',
            emerald: '#059669'
        };
    }

    function findPayloadEl() {
        var nodes = document.querySelectorAll('[id^="lead-analytics-chart-data-"]');
        if (!nodes.length) return null;
        return nodes[nodes.length - 1];
    }

    function formatHours(value) {
        var totalMinutes = Math.round((Number(value) || 0) * 60);
        var hours = Math.floor(totalMinutes / 60);
        var minutes = totalMinutes % 60;

        if (hours > 0 && minutes > 0) {
            return hours + 'h ' + minutes + 'm';
        }

        if (hours > 0) {
            return hours + 'h';
        }

        return minutes + 'm';
    }

    function buildChartsFromPayload(payload) {
        destroyCharts();

        var p = palette();
        if (!payload || !window.Chart) return;

        /* Completed tasks */
        var completed = payload.completed;
        var completedEl = document.getElementById('chart-completed-lead-' + window.__leadAnalyticsTeamKey);
        if (completed && completed.labels && completed.labels.length && completedEl) {
            charts.push(new Chart(completedEl.getContext('2d'), {
                type: 'line',
                data: {
                    labels: completed.labels,
                    datasets: [{
                        label: 'Completed tasks',
                        data: completed.values,
                        borderColor: p.emerald,
                        backgroundColor: 'rgba(5,150,105,0.16)',
                        borderWidth: 2.5,
                        fill: true,
                        stepped: true,
                        tension: 0,
                        pointRadius: 2.5,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: {
                            grid: { color: 'rgba(148,163,184,0.12)' },
                            ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: { display: true, text: 'Completed tasks' },
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { color: 'rgba(148,163,184,0.18)' }
                        }
                    },
                    plugins: { legend: { display: false } }
                }
            }));
        }

        /* Donut */
        var dn = payload.donut;
        var donutEl = document.getElementById('chart-donut-lead-' + window.__leadAnalyticsTeamKey);
        if (dn && donutEl && (dn.on_time + dn.overdue) > 0) {
            charts.push(new Chart(donutEl.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: dn.labels,
                    datasets: [{
                        data: [dn.on_time, dn.overdue],
                        backgroundColor: [p.teal, p.rose],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var v = ctx.raw;
                                    var total = dn.on_time + dn.overdue || 1;
                                    var pct = Math.round((v / total) * 100);
                                    return ctx.label + ': ' + v + ' (' + pct + '%)';
                                }
                            }
                        }
                    },
                    cutout: '62%'
                }
            }));
        }

        /* Velocity */
        var velocity = payload.velocity;
        var velocityEl = document.getElementById('chart-velocity-lead-' + window.__leadAnalyticsTeamKey);
        if (velocity && velocity.labels && velocity.labels.length && velocity.datasets && velocityEl) {
            charts.push(new Chart(velocityEl.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: velocity.labels,
                    datasets: velocity.datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748b', maxRotation: 0, autoSkip: true, maxTicksLimit: 10 }
                        },
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Hours logged' },
                            ticks: { precision: 0 },
                            grid: { color: 'rgba(148,163,184,0.18)' }
                        }
                    },
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return ctx.dataset.label + ': ' + formatHours(ctx.raw);
                                }
                            }
                        }
                    }
                }
            }));
        }
    }

    function initCharts() {
        var payloadNode = findPayloadEl();
        if (!payloadNode) return;
        var txt = payloadNode.textContent || payloadNode.innerText || '{}';
        var payload;
        try { payload = JSON.parse(txt); } catch (e) { return; }
        var match = /^lead-analytics-chart-data-(.+)$/.exec(payloadNode.id);
        window.__leadAnalyticsTeamKey = match ? match[1] : '';

        destroyCharts();

        window.requestAnimationFrame(function () {
            buildChartsFromPayload(payload);
        });
    }

    window.addEventListener('load', initCharts);

    document.addEventListener('livewire:initialized', function () {
        Livewire.hook('morph.updated', function () {
            window.requestAnimationFrame(initCharts);
        });
    });

    document.addEventListener('livewire:navigated', function () {
        window.requestAnimationFrame(initCharts);
    });
})();
</script>
@endpush
@endonce
