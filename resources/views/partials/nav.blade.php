@php
    $links = [
        ['route' => 'home',        'label' => 'Scoreboard'],
        ['route' => 'spread',      'label' => 'Spread'],
        ['route' => 'size-curve',  'label' => 'Size curve'],
        ['route' => 'corridors',   'label' => 'Corridors'],
        ['route' => 'reliability', 'label' => 'Reliability'],
        ['route' => 'settlements', 'label' => 'Settlements'],
        ['route' => 'thesis',      'label' => 'Thesis'],
    ];
@endphp

<nav x-data="{ open: false }"
     class="sticky top-0 z-40 border-b border-viz-grid bg-viz-surface/95 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center gap-4 px-6 py-3">

        <a href="{{ route('home') }}" wire:navigate
           class="shrink-0 text-sm font-semibold tracking-tight text-viz-ink">
            Tripwire
        </a>

        {{-- Desktop links --}}
        <div class="hidden flex-1 items-center gap-1 lg:flex">
            @foreach ($links as $link)
                @php($active = request()->routeIs($link['route']))
                <a href="{{ route($link['route']) }}" wire:navigate
                   @if ($active) aria-current="page" @endif
                   @class([
                       'rounded-md px-2.5 py-1.5 text-sm transition-colors',
                       'bg-viz-grid font-medium text-viz-ink' => $active,
                       'text-viz-ink-muted hover:text-viz-ink' => ! $active,
                   ])>{{ $link['label'] }}</a>
            @endforeach
        </div>

        <div class="ms-auto flex items-center gap-2 lg:ms-0">
            <flux:radio.group x-data variant="segmented" size="sm" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun" />
                <flux:radio value="dark" icon="moon" />
                <flux:radio value="system" icon="computer-desktop" />
            </flux:radio.group>

            {{-- Mobile toggle --}}
            <button type="button" x-on:click="open = ! open"
                    :aria-expanded="open.toString()" aria-controls="nav-menu"
                    aria-label="Toggle navigation"
                    class="rounded-md p-1.5 text-viz-ink-muted hover:text-viz-ink lg:hidden">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.75"
                     viewBox="0 0 24 24" aria-hidden="true">
                    <path x-show="! open" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
                    <path x-show="open" x-cloak stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div id="nav-menu" x-show="open" x-cloak x-transition.opacity.duration.150ms
         x-on:click.outside="open = false"
         class="border-t border-viz-grid px-6 pb-3 lg:hidden">
        @foreach ($links as $link)
            @php($active = request()->routeIs($link['route']))
            <a href="{{ route($link['route']) }}" wire:navigate
               x-on:click="open = false"
               @if ($active) aria-current="page" @endif
               @class([
                   'block rounded-md px-3 py-2 text-sm',
                   'bg-viz-grid font-medium text-viz-ink' => $active,
                   'text-viz-ink-muted' => ! $active,
               ])>{{ $link['label'] }}</a>
        @endforeach
    </div>
</nav>
