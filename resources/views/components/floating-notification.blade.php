@props([
    'message',
    'dismiss' => null,
    'type' => 'success',
])

@php
    $styles = match ($type) {
        'error' => 'bg-red-50 border-red-200 text-red-700',
        'warning' => 'bg-amber-50 border-amber-200 text-amber-700',
        default => 'bg-green-50 border-green-200 text-green-700',
    };

    $buttonStyles = match ($type) {
        'error' => 'text-red-500 hover:text-red-700',
        'warning' => 'text-amber-500 hover:text-amber-700',
        default => 'text-green-500 hover:text-green-700',
    };
@endphp

<div class="fixed top-5 right-5 left-5 sm:left-auto z-50 sm:w-full sm:max-w-md pointer-events-none">
    <div x-data="{ show: true }"
         x-init="setTimeout(() => show = false, 3000); @if($dismiss) setTimeout(() => $wire.{{ $dismiss }}(), 3300); @endif"
         x-show="show"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-4"
         x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100 translate-y-0 sm:translate-x-0"
         x-transition:leave-end="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-4"
         role="status"
         aria-live="polite"
         aria-atomic="true"
         class="pointer-events-auto flex items-center justify-between gap-4 rounded-lg border px-4 py-3 text-sm shadow-lg {{ $styles }}">
        <span class="leading-5">{{ $message }}</span>

        <button type="button"
                @click="show = false"
                class="{{ $buttonStyles }} transition"
                aria-label="Dismiss notification">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
