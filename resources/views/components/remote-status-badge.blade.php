@props(['status' => 'Unknown'])

@php
    $normalized = strtolower(trim((string) $status));
    $styles = match ($normalized) {
        'online', 'running', 'succeeded', 'active', 'built' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'paused', 'pending', 'delivered', 'acknowledged', 'building' => 'bg-amber-50 text-amber-700 border-amber-200',
        'offline', 'stopped', 'cancelled', 'idle', 'inactive', 'used' => 'bg-gray-100 text-gray-600 border-gray-200',
        'failed', 'revoked', 'expired', 'error', 'critical' => 'bg-rose-50 text-rose-700 border-rose-200',
        'warning' => 'bg-orange-50 text-orange-700 border-orange-200',
        'info' => 'bg-sky-50 text-sky-700 border-sky-200',
        default => 'bg-slate-100 text-slate-600 border-slate-200',
    };

    $dotColor = match ($normalized) {
        'online', 'running', 'succeeded', 'active', 'built' => 'bg-emerald-500',
        'paused', 'pending', 'delivered', 'acknowledged', 'building' => 'bg-amber-500',
        'failed', 'revoked', 'expired', 'error', 'critical' => 'bg-rose-500',
        'warning' => 'bg-orange-500',
        'info' => 'bg-sky-500',
        default => 'bg-gray-400',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border {$styles}"]) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }}"></span>
    <span>{{ $status }}</span>
</span>
