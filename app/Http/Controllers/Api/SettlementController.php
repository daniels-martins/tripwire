<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $corridor = $request->query('corridor');

        $query = Settlement::query()
            ->selectRaw('corridor, COUNT(*) as count, AVG(slippage_bps) as avg_slippage_bps')
            ->groupBy('corridor')
            ->orderBy('corridor');

        if ($corridor) {
            $query->where('corridor', $corridor);
        }

        return response()->json(['settlements' => $query->get()]);
    }
}
