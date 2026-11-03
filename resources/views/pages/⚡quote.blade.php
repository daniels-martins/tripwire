<?php

use App\Services\LiveQuoteService;
use App\Services\TokenRegistry;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every corridor NEAR can route, quoted on demand.
 *
 * The scheduled sweep covers a curated handful so the charts have history.
 * This covers the rest -- 215 assets across 38 chains -- and costs nothing
 * until somebody asks, so the polling budget is untouched. Corridors no
 * comparator serves are still offered: hiding them would make NEAR look
 * narrower than it is, which is the opposite of what the data shows.
 */
new #[Layout('layouts.public')] #[Title('Quote a corridor')] class extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $amount = '1000';

    public ?array $result = null;

    public ?string $notice = null;

    public bool $quoting = false;

    #[Computed]
    public function options(): array
    {
        $groups = [];

        foreach (app(TokenRegistry::class)->byChain() as $chain => $tokens) {
            foreach ($tokens as $token) {
                // Twenty-odd chains carry a USDC. Without the chain on the
                // option itself, a closed select just reads "USDC" and the
                // person cannot tell which one they picked.
                $groups[$chain][] = [
                    'id' => $token['asset_id'],
                    'label' => $token['symbol'].' · '.$chain,
                ];
            }
        }

        return $groups;
    }

    #[Computed]
    public function registryEmpty(): bool
    {
        return app(TokenRegistry::class)->isEmpty();
    }

    public function quote(): void
    {
        $this->result = null;
        $this->notice = null;

        $amount = (float) str_replace([',', ' '], '', $this->amount);

        if ($this->from === '' || $this->to === '') {
            $this->notice = 'Pick an asset on both sides.';

            return;
        }

        if ($this->from === $this->to) {
            $this->notice = 'Those are the same asset.';

            return;
        }

        if ($amount < 1 || $amount > 1_000_000) {
            $this->notice = 'Enter an amount between $1 and $1,000,000.';

            return;
        }

        $this->quoting = true;

        try {
            $result = app(LiveQuoteService::class)->quote($this->from, $this->to, $amount);
        } finally {
            $this->quoting = false;
        }

        if (! $result) {
            $this->notice = 'Could not build that corridor.';

            return;
        }

        $this->result = $result;
    }
}; ?>

<div class="mx-auto max-w-6xl px-6 py-8">
    <header class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">Quote a corridor</h1>
        <p class="mt-1 max-w-2xl text-sm text-viz-ink-muted">
            Any asset NEAR can route, at any size, priced live against the same reference the
            charts use. Nothing here is polled on a schedule &mdash; it runs when you ask.
        </p>
    </header>

    @if ($this->registryEmpty)
        <div class="rounded-xl border border-viz-grid bg-viz-surface p-4 text-sm text-viz-ink-muted">
            The asset registry has not been built yet. Run <code class="rounded bg-viz-grid px-1">registry:sync</code>.
        </div>
    @else
        <div class="grid gap-3 rounded-xl border border-viz-grid bg-viz-surface p-4 sm:grid-cols-[1fr_1fr_auto_auto]">
            <label class="block">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-viz-ink-muted">From</span>
                <select wire:model="from" class="w-full">
                    <option value="">Select an asset…</option>
                    @foreach ($this->options as $chain => $tokens)
                        <optgroup label="{{ strtoupper($chain) }}">
                            @foreach ($tokens as $t)
                                <option value="{{ $t['id'] }}">{{ $t['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-viz-ink-muted">To</span>
                <select wire:model="to" class="w-full">
                    <option value="">Select an asset…</option>
                    @foreach ($this->options as $chain => $tokens)
                        <optgroup label="{{ strtoupper($chain) }}">
                            @foreach ($tokens as $t)
                                <option value="{{ $t['id'] }}">{{ $t['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-viz-ink-muted">Amount (USD)</span>
                <input type="text" inputmode="decimal" wire:model="amount"
                       class="tabular w-full sm:w-32" placeholder="1000">
            </label>

            <div class="flex items-end">
                <button type="button" wire:click="quote" wire:loading.attr="disabled"
                        class="w-full rounded-lg border border-viz-grid bg-viz-bg px-4 py-2 text-sm font-medium
                               hover:bg-viz-grid disabled:opacity-50 sm:w-auto">
                    <span wire:loading.remove wire:target="quote">Compare routes</span>
                    <span wire:loading wire:target="quote">Asking every route…</span>
                </button>
            </div>
        </div>

        @if ($notice)
            <p class="mt-3 text-sm text-viz-status-warning">{{ $notice }}</p>
        @endif

        @if ($result)
            <h2 class="mt-8 mb-2 text-sm font-semibold">{{ $result['corridor'] }}</h2>
            <div class="overflow-x-auto rounded-xl border border-viz-grid bg-viz-surface">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-viz-grid text-left text-xs uppercase tracking-wide text-viz-ink-muted">
                            <th class="px-4 py-2.5 font-medium">Route</th>
                            <th class="px-4 py-2.5 text-right font-medium">You receive</th>
                            <th class="px-4 py-2.5 text-right font-medium">Spread</th>
                            <th class="px-4 py-2.5 text-right font-medium">Latency</th>
                            <th class="px-4 py-2.5 text-right font-medium">Swap</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result['rows'] as $row)
                            <tr wire:key="q-{{ $row['provider'] }}" class="border-b border-viz-grid/60 last:border-0">
                                <td class="px-4 py-2.5">
                                    <span class="mr-2 inline-block h-2.5 w-2.5 rounded-full align-middle"
                                          style="background: var(--color-series-{{ $row['slot'] }})"></span>
                                    {{ $row['name'] }}
                                </td>
                                <td class="tabular px-4 py-2.5 text-right">
                                    @if ($row['quoted_output'] !== null)
                                        {{ rtrim(rtrim(number_format($row['quoted_output'], 6, '.', ','), '0'), '.') }}
                                        <span class="text-viz-ink-soft">{{ $result['to']['symbol'] }}</span>
                                    @else
                                        <span class="text-viz-ink-soft">{{ $row['error'] }}</span>
                                    @endif
                                </td>
                                <td class="tabular px-4 py-2.5 text-right">
                                    {{ $row['spread_bps'] !== null ? number_format($row['spread_bps'], 1).' bps' : '—' }}
                                </td>
                                <td class="tabular px-4 py-2.5 text-right text-viz-ink-soft">
                                    {{ $row['latency_ms'] !== null ? number_format($row['latency_ms']).' ms' : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    @if ($row['platform_url'])
                                        <a href="{{ $row['platform_url'] }}" target="_blank" rel="noopener noreferrer">
                                            Open ↗
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 max-w-3xl text-xs text-viz-ink-muted">
                Quotes are dry runs, so no order exists to hand over. &ldquo;Open&rdquo; takes you to the
                route&rsquo;s own site to set the swap up yourself, where the price will be whatever it is
                when you arrive. A route that cannot reach both chains says so rather than failing.
            </p>
        @endif
    @endif
</div>
