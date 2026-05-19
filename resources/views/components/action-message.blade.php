@props(['on'])

<div class="fixed top-5 right-5 left-5 sm:left-auto z-50 sm:w-full sm:max-w-md pointer-events-none">
    <div x-data="{ shown: false, timeout: null }"
         x-init="@this.on('{{ $on }}', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 3000); })"
         x-show="shown"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-4"
         x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100 translate-y-0 sm:translate-x-0"
         x-transition:leave-end="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-4"
         style="display: none;"
         {{ $attributes->merge([
             'role' => 'status',
             'aria-live' => 'polite',
             'aria-atomic' => 'true',
             'class' => 'pointer-events-auto flex items-center justify-between gap-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 shadow-lg',
         ]) }}>
        <span class="leading-5">{{ $slot->isEmpty() ? 'Saved.' : $slot }}</span>
        <button type="button"
                @click="shown = false"
                class="text-green-500 hover:text-green-700 transition"
                aria-label="Dismiss notification">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
