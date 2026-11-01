<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tripwire;
use Illuminate\Http\JsonResponse;

class TripwireController extends Controller
{
    public function index(): JsonResponse
    {
        $tripwires = Tripwire::all()->map(fn (Tripwire $t) => [
            'name' => $t->name,
            'description' => $t->description,
            'status' => $t->status,
            'threshold' => $t->threshold_value,
            'current' => $t->current_value,
            'last_evaluated' => $t->last_evaluated_at,
        ]);

        return response()->json(['tripwires' => $tripwires]);
    }
}
