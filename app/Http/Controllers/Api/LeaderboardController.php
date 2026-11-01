<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('v_leaderboard')
            ->orderBy('median_spread_bps');

        if ($corridor = $request->query('corridor')) {
            $query->where('corridor', $corridor);
        }

        if ($size = $request->query('size')) {
            $query->where('trade_size', $size);
        }

        return response()->json(['leaderboard' => $query->get()]);
    }
}
