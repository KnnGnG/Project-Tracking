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
@endphp

<div class="py-2">
    <div class="grid h-14 rounded-md bg-gray-50 ring-1 ring-gray-100 overflow-visible"
         style="grid-template-columns: repeat({{ $totalDays }}, minmax(0, 1fr)); grid-template-rows: 1fr;">
        @foreach($ticks as $tick)
            <div class="border-r {{ $tick['major'] ? 'border-gray-300' : 'border-gray-200/60' }}"
                 style="grid-column: {{ $tick['day'] }}; grid-row: 1;"></div>
        @endforeach

        @if($todayDay)
            <div class="pointer-events-none z-10 self-stretch border-l-2 border-rose-400/80"
                 style="grid-column: {{ $todayDay }}; grid-row: 1;"></div>
        @endif

        @unless($row['hidePrimary'] ?? false)
            <div x-data="{
                    open: false,
                    x: 0,
                    y: 0,
                    place(event) {
                        const tipWidth = this.$refs.tip?.offsetWidth || 260;
                        const tipHeight = this.$refs.tip?.offsetHeight || 120;
                        this.x = Math.min(event.clientX + 14, window.innerWidth - tipWidth - 12);
                        this.y = Math.min(event.clientY + 14, window.innerHeight - tipHeight - 12);
                    }
                }"
                 @mouseenter="open = true; $nextTick(() => place($event))"
                 @mousemove="place($event)"
                 @mouseleave="open = false"
                 @focus="open = true; $nextTick(() => { const rect = $el.getBoundingClientRect(); place({ clientX: rect.left, clientY: rect.bottom }) })"
                 @blur="open = false"
                 class="group relative z-20 mt-1 self-start h-6 rounded-md border px-2.5 text-[11px] font-semibold leading-6 text-white shadow-sm outline-none {{ $barClass }}"
                 style="grid-column: {{ $row['startDay'] }} / span {{ $row['span'] }}; grid-row: 1;"
                 tabindex="0"
                 aria-label="{{ $row['tooltip'] }}">
                <div class="flex min-w-0 items-center gap-1.5 truncate">
                    <span class="rounded bg-white/20 px-1.5 py-0.5 text-[9px] uppercase tracking-wide leading-none">
                        {{ $row['label'] }}
                    </span>
                    <span class="truncate">{{ $row['title'] }}</span>
                    @if(!empty($row['statusLabel']))
                        <span class="hidden rounded bg-white/20 px-1.5 py-0.5 text-[9px] uppercase tracking-wide leading-none sm:inline">
                            {{ $row['statusLabel'] }}
                        </span>
                    @endif
                </div>
                <template x-teleport="body">
                    <div x-ref="tip"
                         x-cloak
                         x-show="open"
                         x-transition.opacity.duration.100ms
                         class="pointer-events-none fixed z-[99999] w-max max-w-xs rounded-lg border border-gray-200 bg-white px-3 py-2 text-left text-[11px] font-medium leading-5 text-gray-600 shadow-xl ring-1 ring-black/5"
                         :style="`left: ${x}px; top: ${y}px;`">
                        @foreach(($row['tooltipLines'] ?? [$row['tooltip']]) as $line)
                            <p class="{{ $loop->first ? 'font-semibold text-gray-900' : '' }}">{{ $line }}</p>
                        @endforeach
                    </div>
                </template>
            </div>
        @endunless

        @foreach(($row['segments'] ?? collect()) as $segment)
            @php
                $segmentClass = match($segment['kind'] ?? null) {
                    'project' => 'bg-indigo-600 border-indigo-700',
                    'task' => 'bg-amber-500 border-amber-600',
                    'actual' => 'bg-emerald-500 border-emerald-600',
                    'general' => 'bg-emerald-500 border-emerald-600',
                    default => 'bg-gray-400 border-gray-500',
                };
            @endphp
            <div x-data="{
                    open: false,
                    x: 0,
                    y: 0,
                    place(event) {
                        const tipWidth = this.$refs.tip?.offsetWidth || 260;
                        const tipHeight = this.$refs.tip?.offsetHeight || 120;
                        this.x = Math.min(event.clientX + 14, window.innerWidth - tipWidth - 12);
                        this.y = Math.min(event.clientY + 14, window.innerHeight - tipHeight - 12);
                    }
                }"
                 @mouseenter="open = true; $nextTick(() => place($event))"
                 @mousemove="place($event)"
                 @mouseleave="open = false"
                 @focus="open = true; $nextTick(() => { const rect = $el.getBoundingClientRect(); place({ clientX: rect.left, clientY: rect.bottom }) })"
                 @blur="open = false"
                 class="relative z-30 mb-1 self-end h-5 rounded-md border px-2 text-[10px] font-semibold leading-5 text-white shadow-sm outline-none {{ $segmentClass }}"
                 style="grid-column: {{ $segment['startDay'] }} / span {{ $segment['span'] }}; grid-row: 1;"
                 tabindex="0"
                 aria-label="{{ $segment['tooltip'] }}">
                <div class="flex min-w-0 items-center gap-1.5 truncate">
                    <span class="rounded bg-white/20 px-1.5 py-0.5 text-[8px] uppercase tracking-wide leading-none">
                        {{ $segment['label'] }}
                    </span>
                    <span class="truncate">{{ $segment['title'] }}</span>
                </div>
                <template x-teleport="body">
                    <div x-ref="tip"
                         x-cloak
                         x-show="open"
                         x-transition.opacity.duration.100ms
                         class="pointer-events-none fixed z-[99999] w-max max-w-xs rounded-lg border border-gray-200 bg-white px-3 py-2 text-left text-[11px] font-medium leading-5 text-gray-600 shadow-xl ring-1 ring-black/5"
                         :style="`left: ${x}px; top: ${y}px;`">
                        @foreach(($segment['tooltipLines'] ?? [$segment['tooltip']]) as $line)
                            <p class="{{ $loop->first ? 'font-semibold text-gray-900' : '' }}">{{ $line }}</p>
                        @endforeach
                    </div>
                </template>
            </div>
        @endforeach
    </div>
</div>
