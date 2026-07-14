@props([
    'row',
    'ticks',
    'todayDay' => null,
    'totalDays' => 30,
])

@php
    $barClass = match($row['kind']) {
        'project' => 'bg-indigo-600 border-indigo-700',
        'task' => 'bg-amber-500 border-amber-600',
        'actual' => 'bg-emerald-500 border-emerald-600',
        'general' => 'bg-emerald-500 border-emerald-600',
        default => 'bg-gray-400 border-gray-500',
    };
    $segments = collect($row['segments'] ?? []);
    $activityRows = collect($row['activityRows'] ?? []);
    $segmentCount = $segments->count();
    $overflowCount = max($row['segmentOverflowCount'] ?? 0, $row['activityOverflowCount'] ?? 0);
    $hasOverflow = $overflowCount > 0;
    $stackedRowCount = max($segmentCount, $activityRows->count());
    $rowHeightRem = 3.5 + max(0, $stackedRowCount - 1) * 1.35 + ($hasOverflow ? 1.35 : 0);
@endphp

<div class="py-2">
    <div class="grid rounded-md bg-gray-50 ring-1 ring-gray-100 overflow-visible"
         style="height: {{ $rowHeightRem }}rem; grid-template-columns: repeat({{ $totalDays }}, minmax(0, 1fr)); grid-template-rows: 1fr;">
        @foreach($ticks as $tick)
            <div class="border-r {{ $tick['major'] ? 'border-gray-300' : 'border-gray-200/60' }}"
                 style="grid-column: {{ $tick['day'] }}; grid-row: 1;"></div>
        @endforeach

        @if($todayDay)
            <div class="pointer-events-none z-10 self-stretch border-l-2 border-rose-400/80"
                 style="grid-column: {{ $todayDay }}; grid-row: 1;"></div>
        @endif

        @unless($row['hidePrimary'] ?? false)
            <x-timeline-tooltip
                :tooltip-lines="$row['tooltipLines'] ?? [$row['tooltip']]"
                :aria-label="$row['tooltip']"
                class="group relative z-20 mt-1 self-start h-6 rounded-md border px-2.5 text-[11px] font-semibold leading-6 text-white shadow-sm outline-none {{ $barClass }}"
                style="grid-column: {{ $row['startDay'] }} / span {{ $row['span'] }}; grid-row: 1;"
                tabindex="0">
                <div class="flex min-w-0 items-center gap-1.5 truncate">
                    <span class="rounded bg-white/20 px-1.5 py-0.5 text-[9px] uppercase tracking-wide leading-none">
                        {{ $row['label'] }}
                    </span>
                    <span class="truncate">{{ $row['displayTitle'] ?? $row['title'] }}</span>
                </div>
            </x-timeline-tooltip>
        @endunless

        @if($row['kind'] === 'task' && $activityRows->isNotEmpty() && !($row['hideActivity'] ?? false))
            @foreach($activityRows as $activityRow)
                @php $activityDays = collect($activityRow['days']); @endphp
                <div class="relative z-30 grid h-5 self-end overflow-hidden rounded-md border border-gray-500 shadow-sm"
                     style="grid-column: {{ $activityDays->first()['day'] }} / span {{ $activityDays->count() }}; grid-row: 1; grid-template-columns: repeat({{ $activityDays->count() }}, minmax(0, 1fr)); margin-bottom: {{ 0.25 + ($loop->index * 1.35) }}rem;">
                    @foreach($activityDays as $activityDay)
                        <x-timeline-tooltip
                            :tooltip-lines="$activityDay['tooltipLines'] ?? [$activityDay['tooltip']]"
                            :aria-label="$activityDay['tooltip']"
                            class="h-full outline-none {{ $activityDay['state'] === 'logged' ? 'bg-emerald-500' : 'bg-gray-300' }}"
                            tabindex="0" />
                    @endforeach
                </div>
                <div class="pointer-events-none relative z-40 flex h-5 items-center self-end overflow-hidden px-2 text-[10px] font-semibold text-white drop-shadow-sm"
                     style="grid-column: {{ $activityDays->first()['day'] }} / span {{ $activityDays->count() }}; grid-row: 1; margin-bottom: {{ 0.25 + ($loop->index * 1.35) }}rem;">
                    <span class="mr-1.5 rounded bg-white/20 px-1.5 py-0.5 text-[8px] uppercase leading-none">Daily</span>
                    <span class="truncate">{{ $activityRow['memberName'] }}</span>
                </div>
            @endforeach
        @endif

        @foreach($segments as $segment)
            @php
                $segmentClass = match($segment['kind'] ?? null) {
                    'project' => 'bg-indigo-600 border-indigo-700',
                    'task' => 'bg-amber-500 border-amber-600',
                    'actual' => 'bg-emerald-500 border-emerald-600',
                    'general' => 'bg-emerald-500 border-emerald-600',
                    default => 'bg-gray-400 border-gray-500',
                };
            @endphp
            <x-timeline-tooltip
                :tooltip-lines="$segment['tooltipLines'] ?? [$segment['tooltip']]"
                :aria-label="$segment['tooltip']"
                class="relative z-30 self-end h-5 rounded-md border px-2 text-[10px] font-semibold leading-5 text-white shadow-sm outline-none {{ $segmentClass }}"
                style="grid-column: {{ $segment['startDay'] }} / span {{ $segment['span'] }}; grid-row: 1; margin-bottom: {{ 0.25 + ($loop->index * 1.35) }}rem;"
                tabindex="0">
                <div class="flex min-w-0 items-center gap-1.5 truncate">
                    <span class="rounded bg-white/20 px-1.5 py-0.5 text-[8px] uppercase tracking-wide leading-none">
                        {{ $segment['label'] }}
                    </span>
                    <span class="truncate">{{ $segment['displayTitle'] ?? $segment['title'] }}</span>
                </div>
            </x-timeline-tooltip>
        @endforeach
        @if($hasOverflow)
            <div class="relative z-30 self-end justify-self-end rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-500 shadow-sm"
                 style="grid-column: {{ max(1, $totalDays - 5) }} / span 6; grid-row: 1; margin-bottom: {{ 0.25 + ($stackedRowCount * 1.35) }}rem;">
                +{{ $overflowCount }} more
            </div>
        @endif
    </div>
</div>
