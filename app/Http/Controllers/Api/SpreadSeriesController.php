<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpreadSeriesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $corridor = $request->query('corridor');
        $size = $request->query('size', 'medium');
        $provider = $request->query('provider');

        $query = RawQuote::query()
            ->selectRaw("date_trunc('hour', quoted_at) as hour, provider, AVG(spread_bps) as avg_spread_bps")
            ->groupByRaw("date_trunc('hour', quoted_at), provider")
            ->orderByRaw("date_trunc('hour', quoted_at)");

        if ($corridor) {
            $query->where('corridor', $corridor);
        }

        if ($size) {
            $query->where('trade_size', $size);
        }

        if ($provider) {
            $query->where('provider', $provider);
        }

        return response()->json(['series' => $query->get()]);
    }
}
