<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SizeCurveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('mv_size_curve')
            ->orderBy('provider')
            ->orderBy('size_usd');

        if ($corridor = $request->query('corridor')) {
            $query->where('corridor', $corridor);
        }

        return response()->json(['size_curve' => $query->get()]);
    }
}
