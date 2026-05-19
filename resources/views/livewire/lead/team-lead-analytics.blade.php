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
            <div class="bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 text-xs text-amber-900 leading-relaxed">
                <strong>Note:</strong> Historical charts approximate past board states using task timestamps
                (<code class="bg-amber-100/80 px-1 rounded">created_at</code>,
                <code class="bg-amber-100/80 px-1 rounded">start_date</code>,
                <code class="bg-amber-100/80 px-1 rounded">updated_at</code>).
                Tasks in <strong>Review</strong> use the Review status whenever your team applies it from task management or the member board.
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                {{-- Burndown --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-gray-800 mb-1">Task completion (burndown)</h2>
                    <p class="text-xs text-gray-500 mb-4">
                        Open tasks versus calendar time. The dashed grey line shows a linear pace to your project deadline;
                        overlap with teal means you are trending on track.
                        Orange shows calendar days remaining (right axis).
                    </p>
                    <div class="relative h-72">
                        <canvas id="chart-burndown-lead-{{ $selectedTeamId }}"></canvas>
                    </div>
                </div>

                {{-- Donut --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-gray-800 mb-1">Early / on-time vs overdue burden</h2>
                    <p class="text-xs text-gray-500 mb-4">
                        <strong>Early / on-time</strong>: finished by the due date, or still open before the deadline.
                        <strong>Overdue</strong>: delivered late after the due date, or still incomplete past the deadline.
                    </p>
                    <div class="relative max-w-sm mx-auto h-64 flex items-center justify-center">
                        <canvas id="chart-donut-lead-{{ $selectedTeamId }}"></canvas>
                    </div>
                </div>

                {{-- CFD full width --}}
                <div class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-gray-800 mb-1">Cumulative flow (by stage)</h2>
                    <p class="text-xs text-gray-500 mb-4">
                        Stacked area shows where work accumulates across <strong>To do</strong> (pending),
                        <strong>In progress</strong>, <strong>Review</strong>, and <strong>Done</strong>.
                    </p>
                    <div class="relative h-80">
                        <canvas id="chart-cfd-lead-{{ $selectedTeamId }}"></canvas>
                    </div>
                </div>
            </div>

            <script type="application/json" id="lead-analytics-chart-data-{{ $selectedTeamId }}">
                {!! json_encode([
                    'burndown' => $burndown,
                    'cfd' => $cfd,
                    'donut' => $punctuality,
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

    function buildChartsFromPayload(payload) {
        destroyCharts();

        var p = palette();
        if (!payload || !window.Chart) return;

        /* Burndown */
        var bd = payload.burndown;
        if (bd && bd.labels && bd.labels.length && document.getElementById('chart-burndown-lead-' + window.__leadAnalyticsTeamKey)) {
            var ctxBd = document.getElementById('chart-burndown-lead-' + window.__leadAnalyticsTeamKey);
            charts.push(new Chart(ctxBd.getContext('2d'), {
                type: 'line',
                data: {
                    labels: bd.labels,
                    datasets: [
                        {
                            label: 'Open tasks (actual)',
                            data: bd.actual,
                            borderColor: p.teal,
                            backgroundColor: 'rgba(13,148,136,0.06)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.15,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Ideal pace to deadline',
                            data: bd.ideal,
                            borderColor: p.slate400,
                            borderDash: [6, 6],
                            borderWidth: 2,
                            fill: false,
                            tension: 0,
                            pointRadius: 0,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Days until project end',
                            data: bd.days_remaining,
                            borderColor: p.amber,
                            backgroundColor: 'rgba(217,119,6,0.05)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.25,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: { display: true, text: 'Open tasks' },
                            ticks: { precision: 0 }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            title: { display: true, text: 'Days left' },
                            ticks: { precision: 0 }
                        }
                    },
                    plugins: { legend: { position: 'bottom' } }
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

        /* CFD */
        var cfd = payload.cfd;
        var cfdEl = document.getElementById('chart-cfd-lead-' + window.__leadAnalyticsTeamKey);
        if (cfd && cfd.labels && cfd.labels.length && cfdEl) {
            charts.push(new Chart(cfdEl.getContext('2d'), {
                type: 'line',
                data: {
                    labels: cfd.labels,
                    datasets: [
                        {
                            label: 'To do',
                            data: cfd.to_do,
                            stack: 'cfd',
                            borderColor: 'rgba(100,116,139,0.95)',
                            backgroundColor: 'rgba(226,232,240,0.75)',
                            fill: true,
                            tension: 0.05,
                            pointRadius: 0
                        },
                        {
                            label: 'In progress',
                            data: cfd.in_progress,
                            stack: 'cfd',
                            borderColor: 'rgba(37,99,235,0.95)',
                            backgroundColor: 'rgba(147,197,253,0.45)',
                            fill: true,
                            tension: 0.05,
                            pointRadius: 0
                        },
                        {
                            label: 'Review',
                            data: cfd.review,
                            stack: 'cfd',
                            borderColor: 'rgba(245,158,11,0.95)',
                            backgroundColor: 'rgba(251,191,36,0.45)',
                            fill: true,
                            tension: 0.05,
                            pointRadius: 0
                        },
                        {
                            label: 'Done',
                            data: cfd.done,
                            stack: 'cfd',
                            borderColor: 'rgba(5,150,105,0.95)',
                            backgroundColor: 'rgba(5,150,105,0.35)',
                            fill: true,
                            tension: 0.05,
                            pointRadius: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            stacked: true,
                            ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }
                        },
                        y: {
                            stacked: true,
                            title: { display: true, text: 'Tasks' },
                            ticks: { precision: 0 }
                        }
                    },
                    plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } }
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
