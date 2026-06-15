@props([
    'label',
    'value',
    'tone' => 'gray',
    'href' => null,
])

@php
    $toneClasses = match($tone) {
        'indigo' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
        'blue' => 'bg-blue-50 text-blue-700 border-blue-100',
        'green' => 'bg-green-50 text-green-700 border-green-100',
        'amber' => 'bg-amber-50 text-amber-800 border-amber-100',
        'red' => 'bg-red-50 text-red-700 border-red-100',
        default => 'bg-gray-50 text-gray-800 border-gray-100',
    };
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'block rounded-xl border bg-white p-4 shadow-sm transition hover:border-indigo-200']) }}>
@else
    <div {{ $attributes->merge(['class' => 'block rounded-xl border bg-white p-4 shadow-sm transition hover:border-indigo-200']) }}>
@endif
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-2xl font-extrabold leading-none text-gray-900">{{ $value }}</p>
            <p class="mt-1 text-xs font-medium text-gray-400">{{ $label }}</p>
        </div>
        <div class="flex h-10 w-10 items-center justify-center rounded-xl border {{ $toneClasses }}">
            {{ $slot }}
        </div>
    </div>
@if($href)
    </a>
@else
    </div>
@endif
