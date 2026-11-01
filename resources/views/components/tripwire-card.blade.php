@props(['tripwire'])

@php
    $t = $tripwire;
    $operator = $t->config['operator'] ?? '<';
    $value = $t->current_value === null ? null : (float) $t->current_value;
    $threshold = (float) $t->threshold_value;

    // Plot both value and threshold on one axis, so a negative current value
    // (bear_case sits below zero) still has somewhere honest to sit.
    $lo = min(0.0, $value ?? 0.0, $threshold);
    $hi = max($value ?? 0.0, $threshold);
    $span = ($hi - $lo) ?: 1.0;
    $pos = fn (float $v) => max(0, min(100, round(100 * ($v - $lo) / $span, 2)));

    [$tone, $label, $icon] = match ($t->status) {
        'HOLDING' => ['good', 'Holding', 'check'],
        'TRIPPED' => ['critical', 'Tripped', 'alert'],
        default => ['pending', 'Awaiting data', 'clock'],
    };
@endphp

<div class="rounded-xl border border-viz-grid bg-viz-surface p-4">
    <div class="flex items-start justify-between gap-3">
        <h3 class="text-sm font-semibold capitalize">{{ str_replace('_', ' ', $t->name) }}</h3>

        <span @class([
            'inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium',
            'bg-status-good/15 text-status-good' => $tone === 'good',
            'bg-status-critical/15 text-status-critical' => $tone === 'critical',
            'bg-viz-grid text-viz-ink-muted' => $tone === 'pending',
        ])>
            @if ($icon === 'check')
                <svg class="size-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
            @elseif ($icon === 'alert')
                <svg class="size-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
            @else
                <svg class="size-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd"/></svg>
            @endif
            {{ $label }}
        </span>
    </div>

    <p class="mt-1.5 text-xs leading-relaxed text-viz-ink-muted">{{ $t->description }}</p>

    <div class="mt-3 flex items-baseline gap-1.5">
        <span class="text-xl font-semibold tracking-tight">
            {{ $value === null ? 'N/A' : number_format($value, 2) }}
        </span>
        <span class="text-xs text-viz-ink-muted">
            needs {{ $operator }} {{ number_format($threshold, 0) }}
        </span>
    </div>

    {{-- Threshold is a marked line, not an endpoint, so overshoot stays visible. --}}
    <div class="relative mt-3 h-1.5 rounded-full bg-viz-grid" role="img"
         aria-label="{{ $value === null ? 'No value yet' : 'Current '.number_format($value, 2).', threshold '.number_format($threshold, 2) }}">
        @if ($value !== null)
            <div @class([
                    'absolute inset-y-0 left-0 rounded-full',
                    'bg-status-good' => $tone === 'good',
                    'bg-status-critical' => $tone === 'critical',
                 ])
                 style="width: {{ $pos($value) }}%"></div>
        @endif
        <div class="absolute inset-y-[-3px] w-px bg-viz-ink-soft"
             style="left: {{ $pos($threshold) }}%" title="Threshold"></div>
    </div>

    <div class="mt-1.5 flex justify-between text-[10px] text-viz-ink-muted">
        <span class="tabular">{{ number_format($lo, 0) }}</span>
        <span>threshold {{ number_format($threshold, 0) }}</span>
        <span class="tabular">{{ number_format($hi, 0) }}</span>
    </div>
</div>
