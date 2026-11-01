<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $corridor = $request->query('corridor');
        $size = $request->query('size', 'medium');

        $query = RawQuote::query()
            ->selectRaw('provider, COUNT(*) as sample_count, AVG(spread_bps) as avg_spread_bps')
            ->groupBy('provider')
            ->orderBy('avg_spread_bps');

        if ($corridor) {
            $query->where('corridor', $corridor);
        }

        if ($size) {
            $query->where('trade_size', $size);
        }

        return response()->json(['leaderboard' => $query->get()]);
    }
}
