<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Scoreboard')] class extends Component {
    #[Url]
    public string $corridor = 'BTC->USDC.SOL';

    #[Url]
    public int $size = 10000;

    /** Fixed slot per provider, assigned alphabetically. Never reassign. */
    public const SLOTS = [
        'across' => 1, 'jupiter' => 2, 'lifi' => 3,
        'near_intents' => 4, 'relay' => 5, 'thorchain' => 6,
    ];

    public const LABELS = [
        'across' => 'Across', 'jupiter' => 'Jupiter', 'lifi' => 'LiFi',
        'near_intents' => 'NEAR Intents', 'relay' => 'Relay', 'thorchain' => 'Thorchain',
    ];

    #[Computed]
    public function corridors(): array
    {
        return DB::table('v_leaderboard')->distinct()->orderBy('corridor')->pluck('corridor')->all();
    }

    #[Computed]
    public function rows(): array
    {
        return DB::table('v_leaderboard')
            ->where('corridor', $this->corridor)
            ->where('size_usd', $this->size)
            ->orderBy('median_spread_bps')
            ->get()->all();
    }

    #[Computed]
    public function meta(): object
    {
        return DB::table('quotes')->selectRaw(
            "max(polled_at) as as_of, count(*) as n,
             bool_or(raw->>'synthetic' = 'true') as synthetic"
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
                the reference mid, not advertised.
            </p>
            </div>
            <flux:radio.group x-data variant="segmented" size="sm" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun" />
                <flux:radio value="dark" icon="moon" />
                <flux:radio value="system" icon="computer-desktop" />
            </flux:radio.group>
        </header>

        @if ($this->meta->synthetic)
            <div class="mb-6 flex items-start gap-2 rounded-lg border border-status-warning/40 bg-status-warning/10 px-4 py-3">
                <svg class="mt-0.5 size-4 shrink-0 text-status-warning" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                </svg>
                <p class="text-sm">
                    <span class="font-medium">Synthetic data.</span>
                    These rows are seeded placeholders for layout work. No conclusion should be drawn from them.
                </p>
            </div>
        @endif

        {{-- Filters: one row, above the data. --}}
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1">
                <span class="text-xs font-medium text-viz-ink-muted">Corridor</span>
                <select wire:model.live="corridor"
                        class="rounded-md border border-viz-axis bg-viz-surface px-3 py-1.5 text-sm">
                    @foreach ($this->corridors as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1">
                <span class="text-xs font-medium text-viz-ink-muted">Trade size</span>
                <select wire:model.live="size"
                        class="rounded-md border border-viz-axis bg-viz-surface px-3 py-1.5 text-sm">
                    <option value="100">$100</option>
                    <option value="10000">$10,000</option>
                    <option value="100000">$100,000</option>
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
                        <th class="px-4 py-2.5 text-right font-medium">Win rate</th>
                        <th class="px-4 py-2.5 text-right font-medium">Latency</th>
                        <th class="px-4 py-2.5 text-right font-medium">Samples</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows as $i => $row)
                        <tr class="border-b border-viz-grid/60 last:border-0">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <span class="size-2.5 shrink-0 rounded-full"
                                          style="background-color: var(--color-series-{{ self::SLOTS[$row->provider] ?? 1 }})"></span>
                                    <span class="font-medium">{{ self::LABELS[$row->provider] ?? $row->provider }}</span>
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
                            <td class="tabular px-4 py-2.5 text-right">{{ number_format($row->win_rate * 100, 1) }}%</td>
                            <td class="tabular px-4 py-2.5 text-right text-viz-ink-soft">{{ number_format($row->median_latency_ms) }} ms</td>
                            <td class="tabular px-4 py-2.5 text-right text-viz-ink-soft">{{ number_format($row->sample_count) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-viz-ink-muted">
                                No quotes yet for this corridor and size.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-viz-ink-muted">
            Lower spread is better. Win rate is the share of polling rounds where that route
            was cheapest.
            @if ($this->meta->as_of)
                As of {{ \Illuminate\Support\Carbon::parse($this->meta->as_of)->diffForHumans() }},
                {{ number_format($this->meta->n) }} quotes.
            @endif
        </p>
    </div>
</div>
