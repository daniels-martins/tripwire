<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SizeCurveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $corridor = $request->query('corridor');

        $query = RawQuote::query()
            ->selectRaw('provider, trade_size, AVG(spread_bps) as avg_spread_bps, COUNT(*) as sample_count')
            ->groupBy('provider', 'trade_size')
            ->orderBy('provider')
            ->orderByRaw("CASE trade_size WHEN 'small' THEN 1 WHEN 'medium' THEN 2 WHEN 'large' THEN 3 END");

        if ($corridor) {
            $query->where('corridor', $corridor);
        }

        return response()->json(['size_curve' => $query->get()]);
    }
}
