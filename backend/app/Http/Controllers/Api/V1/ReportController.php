<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $reports = Report::query()
            ->with(['reporter', 'reportedUser', 'reviewer'])
            ->where('reporter_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return ReportResource::collection($reports);
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['reported_user_id']) && empty($data['trip_id'])) {
            throw ValidationException::withMessages([
                'reported_user_id' => 'Choose the user or trip you are reporting.',
            ]);
        }

        if (! empty($data['trip_id'])) {
            $trip = Trip::query()->findOrFail($data['trip_id']);
            $isMember = $trip->participants()
                ->where('user_id', $request->user()->id)
                ->exists();

            if (! $isMember) {
                throw ValidationException::withMessages([
                    'trip_id' => 'You can only report trips you took part in.',
                ]);
            }
        }

        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'reported_user_id' => $data['reported_user_id'] ?? null,
            'trip_id' => $data['trip_id'] ?? null,
            'category' => $data['category'],
            'description' => $data['description'],
            'status' => Report::STATUS_OPEN,
        ]);

        return (new ReportResource($report->load(['reporter', 'reportedUser'])))
            ->response()
            ->setStatusCode(201);
    }
}
