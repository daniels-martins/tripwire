<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Reliability')] class extends Component
{
    /** '' means every size pooled together. */
    #[Url]
    public string $size = '';

    public const PROVIDER_LABELS = ['one-click' => '1-Click (NEAR Intents)'];

    #[Computed]
    public function corridorLabels(): array
    {
        return collect(config('corridors.pairs'))->map(fn ($c, $k) => $c['label'] ?? $k)->all();
    }

    #[Computed]
    public function corridors(): array
    {
        return array_keys(config('corridors.pairs'));
    }

    #[Computed]
    public function providers(): array
    {
        return DB::table('raw_quotes')->distinct()->orderBy('provider')->pluck('provider')->all();
    }

    /** [provider][corridor] => ['pct' => float, 'n' => int, 'ok' => int] */
    #[Computed]
    public function grid(): array
    {
        $rows = DB::table('raw_quotes')
            ->selectRaw('provider, corridor, COUNT(*) AS n, COUNT(*) FILTER (WHERE ok) AS ok_n')
            ->when($this->size !== '', fn ($q) => $q->where('trade_size', $this->size))
            ->groupBy('provider', 'corridor')
            ->get();

        $grid = [];
        foreach ($rows as $r) {
            $grid[$r->provider][$r->corridor] = [
                'pct' => $r->n > 0 ? round(100 * $r->ok_n / $r->n, 1) : null,
                'n' => (int) $r->n,
                'ok' => (int) $r->ok_n,
            ];
        }

        return $grid;
    }

    #[Computed]
    public function errors(): array
    {
        return DB::table('raw_quotes')
            ->selectRaw("COALESCE(NULLIF(error, ''), 'unknown') AS message,
                         COUNT(*) AS n, COUNT(DISTINCT corridor) AS corridors")
            ->where('ok', false)
            ->when($this->size !== '', fn ($q) => $q->where('trade_size', $this->size))
            ->groupBy('message')
            ->orderByDesc('n')
            ->get()->all();
    }

    #[Computed]
    public function overall(): object
    {
        return DB::table('raw_quotes')
            ->selectRaw('COUNT(*) AS n, COUNT(*) FILTER (WHERE ok) AS ok_n')
            ->when($this->size !== '', fn ($q) => $q->where('trade_size', $this->size))
            ->first();
    }

    /**
     * Sequential ramp, darker means more reliable. Cells always carry their
     * number too, so colour never has to be read on its own.
     */
    public function rampClass(?float $pct): string
    {
        return match (true) {
            $pct === null => '',
            $pct >= 99 => 'seq-5',
            $pct >= 90 => 'seq-4',
            $pct >= 70 => 'seq-3',
            $pct >= 40 => 'seq-2',
            default => 'seq-1',
        };
    }
}; ?>

<div class="min-h-screen bg-viz-plane text-viz-ink">
    <div class="mx-auto max-w-5xl px-6 py-10">

        <header class="mb-8">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Reliability</h1>
                <p class="mt-1 text-sm text-viz-ink-soft">
                    How often each route actually returns a quote. A corridor nobody can fill is a
                    coverage gap, and gaps are as much a finding as price.
                </p>
            </div>
        </header>

        <div class="mb-4 flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1">
                <span class="text-xs font-medium text-viz-ink-muted">Trade size</span>
                <select wire:model.live="size"
                        class="rounded-md border border-viz-axis bg-viz-surface px-3 py-1.5 text-sm">
                    <option value="">All sizes</option>
                    @foreach (config('corridors.trade_sizes') as $key => $usd)
                        <option value="{{ $key }}">${{ number_format($usd) }} ({{ ucfirst($key) }})</option>
                    @endforeach
                </select>
            </label>
            <div class="pb-2 text-sm text-viz-ink-soft">
                <span class="tabular font-medium">{{ number_format($this->overall->ok_n) }}</span>
                <span class="text-viz-ink-muted">of</span>
                <span class="tabular font-medium">{{ number_format($this->overall->n) }}</span>
                <span class="text-viz-ink-muted">quotes succeeded</span>
            </div>
            <div wire:loading class="pb-2 text-xs text-viz-ink-muted">Updating…</div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-viz-grid bg-viz-surface">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="text-xs text-viz-ink-muted">
                        <th class="sticky left-0 bg-viz-surface px-4 py-2.5 text-left font-medium">Route</th>
                        @foreach ($this->corridors as $c)
                            <th class="px-2 py-2.5 text-center font-medium whitespace-nowrap">
                                {{ $this->corridorLabels[$c] ?? $c }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->providers as $provider)
                        <tr wire:key="prov-{{ $provider }}">
                            <td class="sticky left-0 bg-viz-surface px-4 py-2 text-left font-medium whitespace-nowrap">
                                {{ self::PROVIDER_LABELS[$provider] ?? $provider }}
                            </td>
                            @foreach ($this->corridors as $c)
                                @php($cell = $this->grid[$provider][$c] ?? null)
                                <td class="px-1 py-1 text-center">
                                    @if ($cell === null)
                                        <div class="rounded-md border border-dashed border-viz-grid px-2 py-2 text-xs text-viz-ink-muted"
                                             title="No quotes attempted">—</div>
                                    @else
                                        <div class="tabular rounded-md px-2 py-2 text-xs font-medium {{ $this->rampClass($cell['pct']) }}"
                                             title="{{ $cell['ok'] }}/{{ $cell['n'] }} succeeded">
                                            {{ rtrim(rtrim(number_format($cell['pct'], 1), '0'), '.') }}%
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($this->corridors) + 1 }}" class="px-4 py-10 text-center text-sm text-viz-ink-muted">
                                No quotes recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-viz-ink-muted">
            <span>Success rate</span>
            <div class="flex items-center gap-1">
                <span>low</span>
                @foreach (['seq-1', 'seq-2', 'seq-3', 'seq-4', 'seq-5'] as $cls)
                    <span class="size-4 rounded-sm {{ $cls }}"></span>
                @endforeach
                <span>high</span>
            </div>
            <span class="flex items-center gap-1">
                <span class="size-4 rounded-sm border border-dashed border-viz-grid"></span> not attempted
            </span>
        </div>

        @if (count($this->errors) > 0)
            <div class="mt-6">
                <h2 class="mb-2 text-sm font-semibold">Why quotes failed</h2>
                <div class="overflow-x-auto rounded-xl border border-viz-grid bg-viz-surface">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-viz-grid text-left text-xs text-viz-ink-muted">
                                <th class="px-4 py-2.5 font-medium">Error</th>
                                <th class="px-4 py-2.5 text-right font-medium">Count</th>
                                <th class="px-4 py-2.5 text-right font-medium">Corridors</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->errors as $e)
                                <tr wire:key="err-{{ md5($e->message) }}" class="border-b border-viz-grid/60 last:border-0">
                                    <td class="px-4 py-2.5">{{ $e->message }}</td>
                                    <td class="tabular px-4 py-2.5 text-right">{{ number_format($e->n) }}</td>
                                    <td class="tabular px-4 py-2.5 text-right text-viz-ink-soft">{{ $e->corridors }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
