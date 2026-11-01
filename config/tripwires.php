<?php

return [

    'definitions' => [
        'competitiveness' => [
            'name' => 'competitiveness',
            'description' => 'NEAR median spread stays below threshold — the network remains price-competitive for cross-chain swaps.',
            'metric' => 'near_median_spread_bps',
            'operator' => '<',
            'threshold' => 50.0,
        ],

        'dominance' => [
            'name' => 'dominance',
            'description' => 'NEAR win rate exceeds threshold across all corridors — NEAR wins the quote more often than not.',
            'metric' => 'near_win_rate_pct',
            'operator' => '>',
            'threshold' => 40.0,
        ],

        'execution' => [
            'name' => 'execution',
            'description' => 'Settlement slippage stays below threshold — users receive close to what they were quoted.',
            'metric' => 'median_slippage_bps',
            'operator' => '<',
            'threshold' => 50.0,
        ],

        'corridor_coverage' => [
            'name' => 'corridor_coverage',
            'description' => 'At least N corridors are actively quoting — the network has broad asset coverage.',
            'metric' => 'active_corridor_count',
            'operator' => '>=',
            'threshold' => 4.0,
        ],

        'bear_case' => [
            'name' => 'bear_case',
            'description' => 'NEAR spread edge over the global median exceeds threshold — the thesis breaks if this advantage disappears.',
            'metric' => 'spread_edge_bps',
            'operator' => '>',
            'threshold' => 5.0,
        ],
    ],

];
