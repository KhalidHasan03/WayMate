<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Geo\Services\GeocodingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    public function search(Request $request, GeocodingService $geocoder): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        return response()->json([
            'data' => $geocoder->search($data['q']),
        ]);
    }
}
