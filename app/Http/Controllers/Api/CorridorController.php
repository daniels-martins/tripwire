<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawQuote;
use Illuminate\Http\JsonResponse;

class CorridorController extends Controller
{
    public function index(): JsonResponse
    {
        $pairs = (array) config('corridors.pairs');
        $providers = collect((array) config('providers'))
            ->filter(fn (array $p) => $p['enabled'])
            ->keys();

        $corridors = collect($pairs)->map(fn (mixed $pair, string $key) => [
            'key' => $key,
            'label' => $pair['label'],
            'from' => $pair['from'],
            'to' => $pair['to'],
            'providers' => RawQuote::where('corridor', $key)
                ->distinct('provider')
                ->pluck('provider'),
        ]);

        return response()->json(['corridors' => $corridors->values()]);
    }
}
