@props([
    'tooltipLines' => [],
    'ariaLabel' => '',
])

<div x-data="{
        open: false,
        x: 0,
        y: 0,
        place(event) {
            const tipWidth = this.$refs.tip?.offsetWidth || 280;
            const tipHeight = this.$refs.tip?.offsetHeight || 180;
            this.x = Math.min(event.clientX + 14, window.innerWidth - tipWidth - 12);
            this.y = Math.min(event.clientY + 14, window.innerHeight - tipHeight - 12);
        }
    }"
     @mouseenter="open = true; $nextTick(() => place($event))"
     @mousemove="place($event)"
     @mouseleave="open = false"
     @focus="open = true; $nextTick(() => { const rect = $el.getBoundingClientRect(); place({ clientX: rect.left, clientY: rect.bottom }) })"
     @blur="open = false"
     aria-label="{{ $ariaLabel }}"
     {{ $attributes }}>
    {{ $slot }}

    <template x-teleport="body">
        <div x-ref="tip"
             x-cloak
             x-show="open"
             x-transition.opacity.duration.100ms
             class="pointer-events-none fixed z-[99999] w-max max-w-xs rounded-lg border border-gray-200 bg-white px-3 py-2 text-left text-[11px] font-medium leading-5 text-gray-600 shadow-xl ring-1 ring-black/5"
             :style="`left: ${x}px; top: ${y}px;`">
            @foreach($tooltipLines as $line)
                <p class="{{ $loop->first ? 'font-semibold text-gray-900' : '' }}">{{ $line }}</p>
            @endforeach
        </div>
    </template>
</div>
