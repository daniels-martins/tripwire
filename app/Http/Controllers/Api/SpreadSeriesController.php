<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpreadSeriesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('mv_spread_series')
            ->orderBy('hour');

        if ($corridor = $request->query('corridor')) {
            $query->where('corridor', $corridor);
        }

        if ($size = $request->query('size')) {
            $query->where('trade_size', $size);
        }

        if ($provider = $request->query('provider')) {
            $query->where('provider', $provider);
        }

        return response()->json(['series' => $query->get()]);
    }
}
