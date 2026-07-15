@props([
    'status' => 'pending',
    'label' => null,
])

@php
    $classes = match($status) {
        'active', 'done', 'completed' => 'bg-emerald-100 text-emerald-700',
        'in_progress' => 'bg-blue-100 text-blue-700',
        'review', 'near_due', 'medium' => 'bg-amber-100 text-amber-800',
        'overdue', 'high', 'at_risk' => 'bg-red-100 text-red-700',
        'on_hold', 'pending', 'low' => 'bg-slate-100 text-slate-600',
        'upcoming' => 'bg-sky-100 text-sky-700',
        'new_assignment' => 'bg-violet-100 text-violet-700',
        'client', 'member' => 'bg-blue-100 text-blue-700',
        'team_lead', 'lead' => 'bg-indigo-100 text-indigo-700',
        'admin' => 'bg-purple-100 text-purple-700',
        default => 'bg-slate-100 text-slate-600',
    };

    $display = $label ?? ucwords(str_replace('_', ' ', $status));
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold '.$classes]) }}>
    {{ $display }}
</span>
