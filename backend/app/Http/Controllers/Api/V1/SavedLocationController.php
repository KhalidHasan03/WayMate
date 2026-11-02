<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavedLocationRequest;
use App\Http\Resources\SavedLocationResource;
use App\Models\SavedLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SavedLocationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return SavedLocationResource::collection(
            $request->user()->savedLocations()
                ->orderByDesc('is_default')
                ->orderBy('label')
                ->get(),
        );
    }

    public function store(SavedLocationRequest $request): JsonResponse
    {
        $location = DB::transaction(function () use ($request) {
            if ($request->boolean('is_default')) {
                $request->user()->savedLocations()->update(['is_default' => false]);
            }

            return $request->user()->savedLocations()->create([
                ...$request->validated(),
                'is_default' => $request->boolean('is_default'),
            ]);
        });

        return (new SavedLocationResource($location))
            ->response()
            ->setStatusCode(201);
    }

    public function update(SavedLocationRequest $request, SavedLocation $location): SavedLocationResource|JsonResponse
    {
        if ($location->user_id !== $request->user()->id) {
            abort(403);
        }

        DB::transaction(function () use ($request, $location) {
            if ($request->boolean('is_default')) {
                $request->user()->savedLocations()
                    ->whereKeyNot($location->id)
                    ->update(['is_default' => false]);
            }

            $location->fill($request->validated());
            $location->is_default = $request->boolean('is_default', $location->is_default);
            $location->save();
        });

        return new SavedLocationResource($location);
    }

    public function destroy(Request $request, SavedLocation $location): JsonResponse
    {
        if ($location->user_id !== $request->user()->id) {
            abort(403);
        }

        $location->delete();

        return response()->json(['message' => 'Saved location removed.']);
    }
}
