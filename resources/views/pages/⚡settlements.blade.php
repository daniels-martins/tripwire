<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Settlements')] class extends Component
{
    #[Url]
    public string $corridor = '';

    #[Computed]
    public function corridorLabels(): array
    {
        return collect(config('corridors.pairs'))->map(fn ($c, $k) => $c['label'] ?? $k)->all();
    }

    #[Computed]
    public function corridors(): array
    {
        return DB::table('settlements')->distinct()->orderBy('corridor')->pluck('corridor')->all();
    }

    #[Computed]
    public function rows(): array
    {
        return DB::table('settlements')
            ->when($this->corridor !== '', fn ($q) => $q->where('corridor', $this->corridor))
            ->orderByDesc('settled_at')
            ->limit(100)
            ->get()->all();
    }

    #[Computed]
    public function summary(): object
    {
        return DB::table('settlements')
            ->selectRaw('COUNT(*) AS n, COUNT(slippage_bps) AS with_slippage,
                         COUNT(DISTINCT corridor) AS corridors,
                         MIN(block_height) AS from_block, MAX(block_height) AS to_block,
                         MAX(settled_at) AS newest')
            ->first();
    }

    /**
     * SettlementIngester writes delivered_amount as null: it is not parsed out of the
     * settlement transaction yet. Until it is, there is no delivered figure to compare
     * a quote against, so the page says so rather than implying a measurement.
     */
    #[Computed]
    public function slippageMeasurable(): bool
    {
        return $this->summary->with_slippage > 0;
    }
}; ?>

<div class="min-h-screen bg-viz-plane text-viz-ink">
    <div class="mx-auto max-w-6xl px-6 py-10">

        <header class="mb-8">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Settlements</h1>
                <p class="mt-1 text-sm text-viz-ink-soft">
                    Real swaps observed on <code class="rounded bg-viz-grid px-1 py-0.5 text-xs">intents.near</code>.
                    Quotes are a promise; these are the transactions that actually happened.
                </p>
            </div>
        </header>

        @unless ($this->slippageMeasurable)
            <div class="mb-6 flex items-start gap-2 rounded-lg border border-status-warning/40 bg-status-warning/10 px-4 py-3">
                <svg class="mt-0.5 size-4 shrink-0 text-status-warning" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                </svg>
                <p class="text-sm">
                    <span class="font-medium">Slippage is not measurable yet.</span>
                    Deposits, transaction hashes and block heights below are read from the chain and
                    can be checked on NearBlocks. The delivered amount is not captured - it is not
                    yet parsed out of the settlement transaction - so there is nothing to compare a
                    quote against. Quoted versus delivered becomes a real check once that lands.
                </p>
            </div>
        @endunless

        <div class="mb-4 flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1">
                <span class="text-xs font-medium text-viz-ink-muted">Corridor</span>
                <select wire:model.live="corridor"
                        class="rounded-md border border-viz-axis bg-viz-surface px-3 py-1.5 text-sm">
                    <option value="">All corridors</option>
                    @foreach ($this->corridors as $c)
                        <option value="{{ $c }}">{{ $this->corridorLabels[$c] ?? $c }}</option>
                    @endforeach
                </select>
            </label>
            <div class="pb-2 text-sm text-viz-ink-soft">
                <span class="tabular font-medium">{{ number_format($this->summary->n) }}</span>
                <span class="text-viz-ink-muted">settlements across</span>
                <span class="tabular font-medium">{{ $this->summary->corridors }}</span>
                <span class="text-viz-ink-muted">corridors</span>
            </div>
            <div wire:loading class="pb-2 text-xs text-viz-ink-muted">Updating…</div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-viz-grid bg-viz-surface">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-viz-grid text-left text-xs text-viz-ink-muted">
                        <th class="px-4 py-2.5 font-medium">Transaction</th>
                        <th class="px-4 py-2.5 font-medium">Corridor</th>
                        <th class="px-4 py-2.5 text-right font-medium">Deposited</th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Delivered
                            <span class="font-normal italic">(not captured)</span>
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">Block</th>
                        <th class="px-4 py-2.5 text-right font-medium">Settled</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows as $row)
                        <tr wire:key="s-{{ $row->tx_hash }}" class="border-b border-viz-grid/60 last:border-0">
                            <td class="px-4 py-2.5">
                                @if (\App\Support\DemoData::isDemoHash($row->tx_hash))
                                    <span class="font-mono text-xs text-viz-ink-muted"
                                          title="Seeded demo row - not a real transaction">
                                        {{ substr($row->tx_hash, 0, 10) }}… <span class="not-italic">(demo)</span>
                                    </span>
                                @else
                                    <a href="https://nearblocks.io/txns/{{ $row->tx_hash }}"
                                       target="_blank" rel="noopener noreferrer"
                                       class="font-mono text-xs underline decoration-dotted underline-offset-2 hover:decoration-solid">
                                        {{ substr($row->tx_hash, 0, 10) }}…
                                    </a>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">{{ $this->corridorLabels[$row->corridor] ?? $row->corridor }}</td>
                            <td class="tabular px-4 py-2.5 text-right">{{ rtrim(rtrim(number_format($row->quoted_amount, 6), '0'), '.') }}</td>
                            <td class="tabular px-4 py-2.5 text-right text-viz-ink-soft">
                                {{ $row->delivered_amount === null ? ' - ' : rtrim(rtrim(number_format($row->delivered_amount, 4), '0'), '.') }}
                            </td>
                            <td class="tabular px-4 py-2.5 text-right text-viz-ink-soft">{{ number_format($row->block_height) }}</td>
                            <td class="tabular px-4 py-2.5 text-right text-viz-ink-soft whitespace-nowrap">
                                {{ \Illuminate\Support\Carbon::parse($row->settled_at)->format('H:i:s') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-viz-ink-muted">
                                No settlements ingested yet. Run
                                <code class="rounded bg-viz-grid px-1.5 py-0.5">php artisan settlements:ingest</code>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-viz-ink-muted">
            Transaction hashes link to NearBlocks so any row here can be checked independently.
            @if ($this->summary->n > 0)
                Blocks {{ number_format($this->summary->from_block) }} - {{ number_format($this->summary->to_block) }}.
            @endif
        </p>
    </div>
</div>
