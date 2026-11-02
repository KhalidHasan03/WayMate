<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleDocumentRequest;
use App\Http\Resources\VehicleDocumentResource;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VehicleDocumentController extends Controller
{
    public function store(VehicleDocumentRequest $request, Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('update', $vehicle);

        $file = $request->file('file');
        $path = $file->storeAs(
            'vehicles/'.$vehicle->id,
            Str::uuid()->toString().'.'.$file->getClientOriginalExtension(),
            'local',
        );

        $document = $vehicle->documents()->create([
            'type' => $request->string('type')->toString(),
            'file_path' => $path,
            'expires_at' => $request->date('expires_at'),
            'status' => VehicleDocument::STATUS_PENDING,
        ]);

        return (new VehicleDocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(VehicleDocument $document): JsonResponse
    {
        Gate::authorize('delete', $document);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Vehicle document removed.']);
    }
}
