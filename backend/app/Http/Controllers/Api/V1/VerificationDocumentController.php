<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerificationDocumentRequest;
use App\Http\Resources\VerificationDocumentResource;
use App\Models\VerificationDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VerificationDocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return VerificationDocumentResource::collection(
            $request->user()->verificationDocuments()->latest()->get(),
        );
    }

    public function store(VerificationDocumentRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $path = $file->storeAs(
            'verification/'.$request->user()->id,
            Str::uuid()->toString().'.'.$file->getClientOriginalExtension(),
            'local',
        );

        $document = $request->user()->verificationDocuments()->create([
            'type' => $request->string('type')->toString(),
            'number_encrypted' => $request->input('number'),
            'file_path' => $path,
            'expires_at' => $request->date('expires_at'),
            'status' => VerificationDocument::STATUS_PENDING,
        ]);

        return (new VerificationDocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(VerificationDocument $document): JsonResponse
    {
        Gate::authorize('delete', $document);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Document removed.']);
    }
}
