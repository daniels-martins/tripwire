<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Scoreboard')] class extends Component {
    #[Url]
    public string $corridor = '';

    #[Url]
    public string $size = 'medium';

    public const PROVIDER_SLOTS = [
        'one-click' => 1,
    ];

    public const PROVIDER_LABELS = [
        'one-click' => '1-Click (NEAR Intents)',
    ];

    public function mount(): void
    {
        if ($this->corridor === '') {
            $this->corridor = $this->corridors[0] ?? 'BTC_USDC';
        }
    }

    #[Computed]
    public function corridors(): array
    {
        return DB::table('raw_quotes')
            ->where('ok', true)
            ->distinct()
            ->orderBy('corridor')
            ->pluck('corridor')
            ->all();
    }

    #[Computed]
    public function corridorLabels(): array
    {
        $pairs = config('corridors.pairs');
        $labels = [];
        foreach ($pairs as $key => $config) {
            $labels[$key] = $config['label'] ?? $key;
        }

        return $labels;
    }

    #[Computed]
    public function sizeUsd(): int
    {
        return config("corridors.trade_sizes.{$this->size}", 1000);
    }

    #[Computed]
    public function rows(): array
    {
        return DB::table('raw_quotes')
            ->selectRaw("
                provider,
                COUNT(*) as sample_count,
                (percentile_cont(0.5) WITHIN GROUP (ORDER BY spread_bps))::numeric(12,4) as median_spread_bps,
                (percentile_cont(0.5) WITHIN GROUP (ORDER BY latency_ms))::integer as median_latency_ms,
                MAX(quoted_at) as last_seen_at
            ")
            ->where('corridor', $this->corridor)
            ->where('trade_size', $this->size)
            ->where('ok', true)
            ->whereNotNull('spread_bps')
            ->groupBy('provider')
            ->orderBy('median_spread_bps')
            ->get()
            ->all();
    }

    #[Computed]
    public function meta(): object
    {
        return DB::table('raw_quotes')->selectRaw(
            "MAX(quoted_at) as as_of, COUNT(*) as n, COUNT(*) FILTER (WHERE ok = true) as ok_count"
        )->first();
    }
}; ?>

<div class="min-h-screen bg-viz-plane text-viz-ink">
    <div class="mx-auto max-w-5xl px-6 py-10">

        <header class="mb-8 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Tripwire</h1>
                <p class="mt-1 text-sm text-viz-ink-soft">
                    Which cross-chain route actually gives you the best price — measured against
                    the CEX reference mid, not advertised.
                </p>
            </div>
            <flux:radio.group x-data variant="segmented" size="sm" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun" />
                <flux:radio value="dark" icon="moon" />
                <flux:radio value="system" icon="computer-desktop" />
            </flux:radio.group>
        </header>

        <div class="mb-4 flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1">
                <span class="text-xs font-medium text-viz-ink-muted">Corridor</span>
                <select wire:model.live="corridor"
                        class="rounded-md border border-viz-axis bg-viz-surface px-3 py-1.5 text-sm">
                    @foreach ($this->corridors as $c)
                        <option value="{{ $c }}">{{ $this->corridorLabels[$c] ?? $c }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1">
                <span class="text-xs font-medium text-viz-ink-muted">Trade size</span>
                <select wire:model.live="size"
                        class="rounded-md border border-viz-axis bg-viz-surface px-3 py-1.5 text-sm">
                    @foreach (config('corridors.trade_sizes') as $key => $usd)
                        <option value="{{ $key }}">${{ number_format($usd) }} ({{ ucfirst($key) }})</option>
                    @endforeach
                </select>
            </label>

            <div wire:loading class="pb-2 text-xs text-viz-ink-muted">Updating…</div>
        </div>

        <div class="overflow-hidden rounded-xl border border-viz-grid bg-viz-surface">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-viz-grid text-left text-xs text-viz-ink-muted">
                        <th class="px-4 py-2.5 font-medium">Route</th>
                        <th class="px-4 py-2.5 text-right font-medium">Median spread</th>
                        <th class="px-4 py-2.5 text-right font-medium">Latency</th>
                        <th class="px-4 py-2.5 text-right font-medium">Samples</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows as $i => $row)
                        <tr wire:key="row-{{ $row->provider }}" class="border-b border-viz-grid/60 last:border-0">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <span class="size-2.5 shrink-0 rounded-full"
                                          style="background-color: var(--color-series-{{ self::PROVIDER_SLOTS[$row->provider] ?? 1 }})"></span>
                                    <span class="font-medium">{{ self::PROVIDER_LABELS[$row->provider] ?? $row->provider }}</span>
                                    @if ($i === 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-status-good/15 px-2 py-0.5 text-[11px] font-medium text-status-good">
                                            <svg class="size-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/>
                                            </svg>
                                            Best
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="tabular px-4 py-2.5 text-right">{{ number_format($row->median_spread_bps, 1) }} bps</td>
                            <td class="tabular px-4 py-2.5 text-right text-viz-ink-soft">{{ $row->median_latency_ms ? number_format($row->median_latency_ms) . ' ms' : '—' }}</td>
                            <td class="tabular px-4 py-2.5 text-right text-viz-ink-soft">{{ number_format($row->sample_count) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-sm text-viz-ink-muted">
                                No quotes yet for this corridor and size. Run <code class="rounded bg-viz-grid px-1.5 py-0.5">php artisan quotes:poll</code> to start collecting data.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-viz-ink-muted">
            Lower spread is better. Spread is measured in basis points vs. the Binance spot mid-price.
            @if ($this->meta->as_of)
                As of {{ \Illuminate\Support\Carbon::parse($this->meta->as_of)->diffForHumans() }},
                {{ number_format($this->meta->ok_count) }}/{{ number_format($this->meta->n) }} successful quotes.
            @endif
        </p>
    </div>
</div>
