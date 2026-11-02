<?php

namespace App\Domains\Trips\Services;

use App\Domains\Notifications\Services\NotificationService;
use App\Models\JoinRequest;
use App\Models\RideOffer;
use App\Models\RideRequest;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\TripParticipant;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JoinService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function joinOffer(User $user, RideOffer $offer, array $data): JoinRequest
    {
        if ($offer->status !== RideOffer::STATUS_OPEN) {
            $this->fail('This offer is no longer open.');
        }

        if ($offer->provider_id === $user->id) {
            $this->fail('You cannot join your own offer.');
        }

        if ($offer->women_only && $user->gender !== 'female') {
            $this->fail('This is a women-only ride.');
        }

        $seats = max(1, (int) ($data['seats'] ?? 1));

        if ($seats > $offer->seatsAvailable()) {
            $this->fail('There are not enough seats left on this offer.');
        }

        if ($offer->expires_at !== null && $offer->expires_at->isPast()) {
            $this->fail('This offer has expired.');
        }

        if ($this->existingJoin($user, rideOfferId: $offer->id)) {
            $this->fail('You already have a pending request for this offer.');
        }

        $departAt = $offer->depart_at;
        $ttlHours = (int) config('waymate.join.offer_ttl_hours', 12);
        $expiresAt = now()->addHours($ttlHours);
        $expiresAt = $departAt->lessThan($expiresAt) ? $departAt->copy() : $expiresAt;

        $join = JoinRequest::create([
            'ride_offer_id' => $offer->id,
            'user_id' => $user->id,
            'role' => JoinRequest::ROLE_PASSENGER,
            'seats' => $seats,
            'start_lat' => $data['start_lat'] ?? $offer->origin_lat,
            'start_lng' => $data['start_lng'] ?? $offer->origin_lng,
            'intended_depart_at' => $data['intended_depart_at'] ?? $departAt,
            'message' => $data['message'] ?? null,
            'consented_contribution_poisha' => $data['consented_contribution_poisha']
                ?? $this->estimatedShare($offer) * $seats,
            'consented_fare_version' => 0,
            'pair_key' => sha1("offer|{$offer->id}|{$user->id}"),
            'service_date' => $departAt->toDateString(),
            'status' => JoinRequest::STATUS_PENDING,
            'expires_at' => $expiresAt,
        ]);

        $this->notifications->joinRequested($join->load(['user', 'rideOffer.provider']));

        return $join;
    }

    public function joinRequest(User $user, RideRequest $request, array $data): JoinRequest
    {
        if ($request->status !== RideRequest::STATUS_OPEN) {
            $this->fail('This request is no longer open.');
        }

        if ($request->creator_id === $user->id) {
            $this->fail('You cannot offer to drive your own request.');
        }

        if ($request->activeTrip()->exists()) {
            $this->fail('Someone is already driving this request.');
        }

        if ($request->women_only && $user->gender !== 'female') {
            $this->fail('This is a women-only request.');
        }

        if (! $user->canProvideRides()) {
            $this->fail('Verify your identity and add an active vehicle before offering to drive.');
        }

        $vehicle = Vehicle::query()
            ->whereKey($data['vehicle_id'] ?? null)
            ->where('user_id', $user->id)
            ->where('status', Vehicle::STATUS_ACTIVE)
            ->first();

        if (! $vehicle) {
            $this->fail('Choose one of your active vehicles.');
        }

        if ($this->existingJoin($user, rideRequestId: $request->id)) {
            $this->fail('You already offered to drive this request.');
        }

        $departAt = $request->depart_at;
        $ttlHours = (int) config('waymate.join.request_ttl_hours', 24);
        $expiresAt = now()->addHours($ttlHours);
        $expiresAt = $departAt->lessThan($expiresAt) ? $departAt->copy() : $expiresAt;

        $join = JoinRequest::create([
            'ride_request_id' => $request->id,
            'user_id' => $user->id,
            'role' => JoinRequest::ROLE_PROVIDER,
            'seats' => (int) $request->seats_needed,
            'vehicle_id' => $vehicle->id,
            'start_lat' => $data['start_lat'] ?? $request->origin_lat,
            'start_lng' => $data['start_lng'] ?? $request->origin_lng,
            'intended_depart_at' => $data['intended_depart_at'] ?? $departAt,
            'message' => $data['message'] ?? null,
            'pair_key' => sha1("request|{$request->id}|{$user->id}"),
            'service_date' => $departAt->toDateString(),
            'status' => JoinRequest::STATUS_PENDING,
            'expires_at' => $expiresAt,
        ]);

        $this->notifications->joinRequested($join->load(['user', 'rideRequest.creator']));

        return $join;
    }

    public function accept(JoinRequest $join, User $actor): Trip
    {
        return DB::transaction(function () use ($join, $actor) {
            $join = JoinRequest::query()->whereKey($join->id)->lockForUpdate()->firstOrFail();

            if ($join->status !== JoinRequest::STATUS_PENDING) {
                $this->fail('This join request has already been decided.');
            }

            if ($join->expires_at !== null && $join->expires_at->isPast()) {
                $join->update(['status' => JoinRequest::STATUS_EXPIRED, 'decided_at' => now()]);
                $this->fail('This join request has expired.');
            }

            $trip = $join->ride_offer_id !== null
                ? $this->acceptForOffer($join, $actor)
                : $this->acceptForRequest($join, $actor);

            $join->update([
                'status' => JoinRequest::STATUS_ACCEPTED,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

            TripEvent::create([
                'trip_id' => $trip->id,
                'actor_id' => $actor->id,
                'event' => 'join_accepted',
                'meta' => ['join_request_id' => $join->id, 'user_id' => $join->user_id],
            ]);

            $this->notifications->joinDecided($join->fresh()->load('user'), true);

            return $trip;
        });
    }

    public function decline(JoinRequest $join, User $actor): JoinRequest
    {
        if ($join->status !== JoinRequest::STATUS_PENDING) {
            $this->fail('This join request has already been decided.');
        }

        $join->update([
            'status' => JoinRequest::STATUS_DECLINED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ]);

        $this->notifications->joinDecided($join->fresh()->load('user'), false);

        return $join;
    }

    public function withdraw(JoinRequest $join, User $user): JoinRequest
    {
        if ($join->user_id !== $user->id) {
            abort(403);
        }

        if ($join->status !== JoinRequest::STATUS_PENDING) {
            $this->fail('Only pending join requests can be withdrawn.');
        }

        $join->update(['status' => JoinRequest::STATUS_WITHDRAWN, 'decided_at' => now()]);

        return $join;
    }

    private function acceptForOffer(JoinRequest $join, User $actor): Trip
    {
        $offer = RideOffer::query()->whereKey($join->ride_offer_id)->lockForUpdate()->firstOrFail();

        if ($offer->provider_id !== $actor->id) {
            abort(403);
        }

        if ($offer->status !== RideOffer::STATUS_OPEN) {
            $this->fail('This offer is no longer open.');
        }

        if ((int) $join->seats > $offer->seatsAvailable()) {
            $this->fail('There are not enough seats left to accept this request.');
        }

        $trip = $offer->activeTrip()->lockForUpdate()->first();

        if (! $trip) {
            $trip = Trip::create([
                'ride_offer_id' => $offer->id,
                'coordinator_id' => $offer->provider_id,
                'provider_id' => $offer->provider_id,
                'vehicle_id' => $offer->vehicle_id,
                'transport_mode' => Trip::MODE_PRIVATE,
                'pool_capacity' => null,
                'seats_allocated' => null,
                'meeting_lat' => $offer->origin_lat,
                'meeting_lng' => $offer->origin_lng,
                'meeting_label' => $offer->origin_label,
                'status' => Trip::STATUS_FORMING,
            ]);

            TripParticipant::create([
                'trip_id' => $trip->id,
                'user_id' => $offer->provider_id,
                'role' => TripParticipant::ROLE_PROVIDER,
                'seats' => 0,
                'status' => TripParticipant::STATUS_CONFIRMED,
                'joined_at' => now(),
            ]);
        }

        if ($trip->participants()->where('user_id', $join->user_id)->exists()) {
            $this->fail('This person is already part of the trip.');
        }

        TripParticipant::create([
            'trip_id' => $trip->id,
            'user_id' => $join->user_id,
            'role' => TripParticipant::ROLE_PASSENGER,
            'seats' => (int) $join->seats,
            'status' => TripParticipant::STATUS_CONFIRMED,
            'joined_at' => now(),
            'consented_fare_version' => (int) ($join->consented_fare_version ?? 0),
        ]);

        $offer->increment('seats_allocated', (int) $join->seats);

        return $trip;
    }

    private function acceptForRequest(JoinRequest $join, User $actor): Trip
    {
        $request = RideRequest::query()->whereKey($join->ride_request_id)->lockForUpdate()->firstOrFail();

        if ($request->creator_id !== $actor->id) {
            abort(403);
        }

        if ($request->status !== RideRequest::STATUS_OPEN) {
            $this->fail('This request is no longer open.');
        }

        if ($request->activeTrip()->exists()) {
            $this->fail('This request already has a driver.');
        }

        if ($join->role !== JoinRequest::ROLE_PROVIDER || $join->vehicle_id === null) {
            $this->fail('Only provider offers can be accepted for a ride request.');
        }

        $vehicle = Vehicle::query()->whereKey($join->vehicle_id)->first();
        $provider = User::query()->whereKey($join->user_id)->firstOrFail();

        if (! $vehicle || $vehicle->user_id !== $provider->id || ! $vehicle->isActive()) {
            $this->fail('The offered vehicle is no longer active.');
        }

        if (! $provider->canProvideRides()) {
            $this->fail('The provider is no longer eligible to drive.');
        }

        $trip = Trip::create([
            'ride_request_id' => $request->id,
            'coordinator_id' => $request->creator_id,
            'provider_id' => $provider->id,
            'vehicle_id' => $vehicle->id,
            'transport_mode' => Trip::MODE_PRIVATE,
            'pool_capacity' => (int) $request->pool_max_seats,
            'seats_allocated' => (int) $request->seats_needed,
            'meeting_lat' => $request->origin_lat,
            'meeting_lng' => $request->origin_lng,
            'meeting_label' => $request->origin_label,
            'status' => Trip::STATUS_FORMING,
        ]);

        TripParticipant::create([
            'trip_id' => $trip->id,
            'user_id' => $request->creator_id,
            'role' => TripParticipant::ROLE_PASSENGER,
            'seats' => (int) $request->seats_needed,
            'status' => TripParticipant::STATUS_CONFIRMED,
            'joined_at' => now(),
        ]);

        TripParticipant::create([
            'trip_id' => $trip->id,
            'user_id' => $provider->id,
            'role' => TripParticipant::ROLE_PROVIDER,
            'seats' => 0,
            'status' => TripParticipant::STATUS_CONFIRMED,
            'joined_at' => now(),
        ]);

        $request->update(['status' => RideRequest::STATUS_IN_PROGRESS]);

        $request->joinRequests()
            ->where('status', JoinRequest::STATUS_PENDING)
            ->whereKeyNot($join->id)
            ->update([
                'status' => JoinRequest::STATUS_DECLINED,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

        return $trip;
    }

    private function existingJoin(User $user, ?int $rideOfferId = null, ?int $rideRequestId = null): bool
    {
        return JoinRequest::query()
            ->where('user_id', $user->id)
            ->when($rideOfferId, fn ($query) => $query->where('ride_offer_id', $rideOfferId))
            ->when($rideRequestId, fn ($query) => $query->where('ride_request_id', $rideRequestId))
            ->whereIn('status', [JoinRequest::STATUS_PENDING, JoinRequest::STATUS_ACCEPTED])
            ->exists();
    }

    private function estimatedShare(RideOffer $offer): int
    {
        $participants = (int) $offer->seats_total + ($offer->provider_participates ? 1 : 0);

        return (int) floor((int) $offer->cost_total_poisha / max(1, $participants));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['join' => $message]);
    }
}
