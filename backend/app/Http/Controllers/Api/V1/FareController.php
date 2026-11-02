<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Fares\Services\FareAdjustmentService;
use App\Domains\Fares\Services\FareService;
use App\Http\Controllers\Controller;
use App\Http\Requests\FareAdjustmentRequest;
use App\Http\Requests\FareRequest;
use App\Http\Resources\FareResource;
use App\Models\Contribution;
use App\Models\FareAdjustment;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FareController extends Controller
{
    public function __construct(
        private readonly FareService $fares,
        private readonly FareAdjustmentService $adjustments,
    ) {}

    public function show(Trip $trip): FareResource
    {
        Gate::authorize('view', $trip);

        $fare = $trip->fare;

        abort_if($fare === null, 404, 'The fare has not been calculated yet.');

        return new FareResource($fare->load([
            'items',
            'contributions.user',
            'collector',
            'adjustments.proposer',
            'adjustments.votes.user',
        ]));
    }

    public function update(FareRequest $request, Trip $trip): FareResource
    {
        Gate::authorize('manageFare', $trip);

        $fare = $this->fares->calculate(
            $trip,
            $request->validated('items'),
            $request->boolean('provider_participates'),
            $request->user(),
        );

        return new FareResource($fare);
    }

    public function agree(Request $request, Trip $trip): FareResource
    {
        Gate::authorize('view', $trip);

        return new FareResource($this->fares->agree($trip, $request->user()));
    }

    public function proposeAdjustment(FareAdjustmentRequest $request, Trip $trip): FareResource
    {
        Gate::authorize('view', $trip);

        $fare = $this->adjustments->propose(
            $trip,
            $request->user(),
            $request->integer('new_total_poisha'),
            $request->validated('reason'),
        );

        return new FareResource($fare);
    }

    public function voteAdjustment(Request $request, Trip $trip, FareAdjustment $adjustment): FareResource
    {
        Gate::authorize('view', $trip);

        $data = $request->validate(['vote' => ['required', Rule::in(['accept', 'reject'])]]);

        $fare = $this->adjustments->vote($trip, $adjustment, $request->user(), $data['vote']);

        return new FareResource($fare);
    }

    public function settle(Request $request, Trip $trip, Contribution $contribution): FareResource
    {
        Gate::authorize('settleFare', $trip);

        $data = $request->validate([
            'status' => ['required', Rule::in([
                Contribution::STATUS_PAID,
                Contribution::STATUS_WAIVED,
                Contribution::STATUS_VOID,
            ])],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $fare = $this->fares->markContribution(
            $trip,
            $contribution,
            $data['status'],
            $request->user(),
            $data['note'] ?? null,
        );

        return new FareResource($fare);
    }
}
