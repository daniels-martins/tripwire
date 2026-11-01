<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettlementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('mv_settlement_accuracy')
            ->orderBy('corridor');

        if ($corridor = $request->query('corridor')) {
            $query->where('corridor', $corridor);
        }

        return response()->json(['settlements' => $query->get()]);
    }
}
