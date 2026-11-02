<?php

namespace App\Domains\Trips\Services;

use App\Domains\Notifications\Services\NotificationService;
use App\Models\RideOffer;
use App\Models\RideRequest;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\TripParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function start(Trip $trip, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $actor) {
            $trip = Trip::query()->whereKey($trip->id)->lockForUpdate()->firstOrFail();

            if ($trip->status !== Trip::STATUS_FORMING && $trip->status !== Trip::STATUS_READY) {
                $this->fail('Only a forming or ready trip can be started.');
            }

            $trip->update([
                'status' => Trip::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);

            $trip->participants()
                ->where('status', TripParticipant::STATUS_CONFIRMED)
                ->update(['status' => TripParticipant::STATUS_CHECKED_IN]);

            $this->event($trip, $actor, 'trip_started');
            $this->notifications->tripLifecycle(
                $trip,
                'trip_started',
                'Your trip has started',
                'The shared ride is now in progress. Drive safely.',
                $actor->id,
            );

            return $trip->fresh($this->relations());
        });
    }

    public function complete(Trip $trip, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $actor) {
            $trip = Trip::query()->whereKey($trip->id)->lockForUpdate()->firstOrFail();

            if ($trip->status !== Trip::STATUS_IN_PROGRESS) {
                $this->fail('Only an in-progress trip can be completed.');
            }

            $trip->update([
                'status' => Trip::STATUS_COMPLETED,
                'completed_at' => now(),
                'tracking_enabled' => false,
                'tracking_stopped_at' => now(),
            ]);

            $participants = $trip->participants()
                ->whereIn('status', [TripParticipant::STATUS_CONFIRMED, TripParticipant::STATUS_CHECKED_IN])
                ->get();

            foreach ($participants as $participant) {
                $participant->update(['status' => TripParticipant::STATUS_COMPLETED]);
                User::query()->whereKey($participant->user_id)->increment('trips_completed');
            }

            if ($trip->ride_request_id !== null) {
                RideRequest::query()->whereKey($trip->ride_request_id)
                    ->update(['status' => RideRequest::STATUS_COMPLETED]);
            }

            if ($trip->ride_offer_id !== null) {
                RideOffer::query()->whereKey($trip->ride_offer_id)
                    ->update(['status' => RideOffer::STATUS_COMPLETED]);
            }

            $this->event($trip, $actor, 'trip_completed');
            $this->notifications->tripLifecycle(
                $trip,
                'trip_completed',
                'Trip completed',
                'Your shared ride is complete. Review the cost share and settle up.',
                $actor->id,
            );

            return $trip->fresh($this->relations());
        });
    }

    public function cancel(Trip $trip, User $actor, ?string $reason = null): Trip
    {
        return DB::transaction(function () use ($trip, $actor, $reason) {
            $trip = Trip::query()->whereKey($trip->id)->lockForUpdate()->firstOrFail();

            if (! $trip->isActive()) {
                $this->fail('Only an active trip can be cancelled.');
            }

            $trip->update([
                'status' => Trip::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason,
                'tracking_enabled' => false,
                'tracking_stopped_at' => now(),
            ]);

            $this->notifications->tripLifecycle(
                $trip,
                'trip_cancelled',
                'Trip cancelled',
                $reason ? "The shared trip was cancelled: {$reason}" : 'The shared trip was cancelled.',
                $actor->id,
            );

            $trip->participants()
                ->whereIn('status', [
                    TripParticipant::STATUS_CONSENT_PENDING,
                    TripParticipant::STATUS_CONFIRMED,
                    TripParticipant::STATUS_CHECKED_IN,
                ])
                ->update(['status' => TripParticipant::STATUS_CANCELLED, 'left_at' => now()]);

            if ($trip->ride_request_id !== null) {
                RideRequest::query()->whereKey($trip->ride_request_id)
                    ->update(['status' => RideRequest::STATUS_CANCELLED]);
            }

            if ($trip->ride_offer_id !== null) {
                RideOffer::query()->whereKey($trip->ride_offer_id)
                    ->update(['status' => RideOffer::STATUS_CANCELLED]);
            }

            $this->event($trip, $actor, 'trip_cancelled', ['reason' => $reason]);

            return $trip->fresh($this->relations());
        });
    }

    private function event(Trip $trip, User $actor, string $event, array $meta = []): void
    {
        TripEvent::create([
            'trip_id' => $trip->id,
            'actor_id' => $actor->id,
            'event' => $event,
            'meta' => $meta ?: null,
        ]);
    }

    private function relations(): array
    {
        return [
            'coordinator',
            'provider',
            'vehicle',
            'participants.user',
            'rideRequest',
            'rideOffer.provider',
            'rideOffer.vehicle',
        ];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['trip' => $message]);
    }
}
