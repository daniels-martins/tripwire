<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawQuote;
use App\Models\Settlement;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'as_of' => now()->toIso8601String(),
            'quote_count' => RawQuote::count(),
            'settlement_count' => Settlement::count(),
            'corridors' => array_keys(config('corridors.pairs')),
            'trade_sizes' => config('corridors.trade_sizes'),
        ]);
    }
}
