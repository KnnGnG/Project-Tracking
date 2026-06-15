@props([
    'status' => 'pending',
    'label' => null,
])

@php
    $classes = match($status) {
        'active', 'done', 'completed' => 'bg-green-100 text-green-700',
        'in_progress' => 'bg-blue-100 text-blue-700',
        'review' => 'bg-amber-100 text-amber-800',
        'overdue', 'high', 'at_risk' => 'bg-red-100 text-red-700',
        'on_hold', 'medium' => 'bg-yellow-100 text-yellow-700',
        'client', 'member' => 'bg-blue-100 text-blue-700',
        'team_lead', 'lead' => 'bg-indigo-100 text-indigo-700',
        'admin' => 'bg-purple-100 text-purple-700',
        default => 'bg-gray-100 text-gray-600',
    };

    $display = $label ?? ucwords(str_replace('_', ' ', $status));
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold '.$classes]) }}>
    {{ $display }}
</span>
