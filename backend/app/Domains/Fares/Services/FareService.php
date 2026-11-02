<?php

namespace App\Domains\Fares\Services;

use App\Domains\Notifications\Services\NotificationService;
use App\Models\Contribution;
use App\Models\FareItem;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\TripFare;
use App\Models\TripParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FareService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function calculate(Trip $trip, array $items, bool $providerParticipates, User $actor): TripFare
    {
        return DB::transaction(function () use ($trip, $items, $providerParticipates, $actor) {
            $fare = TripFare::query()
                ->where('trip_id', $trip->id)
                ->lockForUpdate()
                ->first();

            if ($fare && in_array($fare->status, [TripFare::STATUS_AGREED, TripFare::STATUS_SETTLED], true)) {
                $this->fail('This fare is already agreed. Use an adjustment to change it.');
            }

            $participants = $trip->participants()
                ->whereIn('status', [
                    TripParticipant::STATUS_CONFIRMED,
                    TripParticipant::STATUS_CHECKED_IN,
                    TripParticipant::STATUS_COMPLETED,
                ])
                ->get();

            if ($participants->isEmpty()) {
                $this->fail('There are no confirmed participants on this trip.');
            }

            $units = [];

            foreach ($participants as $participant) {
                $units[$participant->user_id] = $participant->role === TripParticipant::ROLE_PROVIDER
                    ? ($providerParticipates ? 1 : 0)
                    : max(1, (int) $participant->seats);
            }

            $totalUnits = array_sum($units);

            if ($totalUnits < 1) {
                $this->fail('At least one participant must share the cost.');
            }

            $fare ??= new TripFare(['trip_id' => $trip->id]);
            $version = $fare->exists ? (int) $fare->fare_version + 1 : 1;
            $maxVersion = (int) config('waymate.fare.max_fare_version', 3);

            if ($version > $maxVersion) {
                $this->fail('This fare has reached its maximum number of versions.');
            }

            $collectorId = $trip->provider_id ?? $trip->coordinator_id;

            $fare->fill([
                'fare_version' => $version,
                'status' => TripFare::STATUS_AGREEING,
                'provider_participates' => $providerParticipates,
            ])->save();

            $sharedTotal = 0;
            $allTotal = 0;

            $fare->items()->delete();
            $fare->contributions()->where('status', Contribution::STATUS_PENDING)->delete();

            foreach ($items as $item) {
                $amount = max(0, (int) $item['amount_poisha']);
                $split = $item['split_method'] ?? FareItem::SPLIT_EQUAL;

                FareItem::create([
                    'trip_fare_id' => $fare->id,
                    'label' => $item['label'],
                    'amount_poisha' => $amount,
                    'split_method' => $split,
                    'created_by' => $actor->id,
                ]);

                $allTotal += $amount;

                if ($split === FareItem::SPLIT_EQUAL) {
                    $sharedTotal += $amount;
                }
            }

            $base = intdiv($sharedTotal, $totalUnits);
            $remainder = $sharedTotal % $totalUnits;
            $amounts = [];

            foreach ($units as $userId => $userUnits) {
                $amounts[$userId] = $base * $userUnits;
            }

            if ($remainder > 0) {
                $receiver = $collectorId !== null && array_key_exists($collectorId, $units)
                    ? $collectorId
                    : array_key_first($units);
                $amounts[$receiver] += $remainder;
            }

            $fare->fill([
                'estimated_total_poisha' => $allTotal,
                'agreed_total_poisha' => null,
                'participant_count' => count(array_filter($units)),
                'provider_participates' => $providerParticipates,
                'rounding_absorbed_poisha' => $remainder,
                'collector_id' => $collectorId,
                'fare_version' => $version,
                'status' => TripFare::STATUS_AGREEING,
                'agreed_at' => null,
                'settled_at' => null,
            ])->save();

            foreach ($amounts as $userId => $amount) {
                if ($amount <= 0) {
                    continue;
                }

                Contribution::create([
                    'trip_id' => $trip->id,
                    'user_id' => $userId,
                    'fare_version' => $version,
                    'fare_item_id' => null,
                    'kind' => Contribution::KIND_SHARE,
                    'amount_poisha' => $amount,
                    'status' => Contribution::STATUS_PENDING,
                    'note' => "Fare share v{$version}",
                ]);
            }

            $trip->participants()->update(['consented_fare_version' => 0]);

            TripEvent::create([
                'trip_id' => $trip->id,
                'actor_id' => $actor->id,
                'event' => 'fare_calculated',
                'meta' => ['fare_version' => $version, 'total_poisha' => $allTotal],
            ]);

            $this->notifications->fareUpdated(
                $trip,
                'fare_calculated',
                'Cost share ready',
                'Your trip cost share has been calculated. Open trips to review and agree.',
                $actor->id,
            );

            return $fare->fresh($this->fareRelations());
        });
    }

    public function agree(Trip $trip, User $user): TripFare
    {
        return DB::transaction(function () use ($trip, $user) {
            $fare = TripFare::query()->where('trip_id', $trip->id)->lockForUpdate()->first();

            if (! $fare) {
                $this->fail('The fare has not been calculated yet.');
            }

            if ($fare->status !== TripFare::STATUS_AGREEING) {
                $this->fail('This fare is not open for agreement.');
            }

            $participant = $trip->participants()->where('user_id', $user->id)->first();

            if (! $participant || ! in_array($participant->status, [
                TripParticipant::STATUS_CONFIRMED,
                TripParticipant::STATUS_CHECKED_IN,
                TripParticipant::STATUS_COMPLETED,
            ], true)) {
                $this->fail('Only confirmed participants can agree to the fare.');
            }

            $participant->update(['consented_fare_version' => (int) $fare->fare_version]);

            $active = $trip->participants()->whereIn('status', [
                TripParticipant::STATUS_CONFIRMED,
                TripParticipant::STATUS_CHECKED_IN,
                TripParticipant::STATUS_COMPLETED,
            ]);

            $total = (clone $active)->count();
            $agreed = (clone $active)
                ->where('consented_fare_version', '>=', (int) $fare->fare_version)
                ->count();

            if ($total > 0 && $agreed >= $total) {
                $fare->update([
                    'status' => TripFare::STATUS_AGREED,
                    'agreed_total_poisha' => (int) $fare->estimated_total_poisha,
                    'agreed_at' => now(),
                ]);

                TripEvent::create([
                    'trip_id' => $trip->id,
                    'actor_id' => $user->id,
                    'event' => 'fare_agreed',
                    'meta' => ['fare_version' => $fare->fare_version],
                ]);

                $this->notifications->fareUpdated(
                    $trip,
                    'fare_agreed',
                    'Cost share agreed',
                    'Everyone has agreed to the trip cost share.',
                    $user->id,
                );
            }

            return $fare->fresh($this->fareRelations());
        });
    }

    public function markContribution(
        Trip $trip,
        Contribution $contribution,
        string $status,
        User $actor,
        ?string $note = null,
    ): TripFare {
        if ((int) $contribution->trip_id !== (int) $trip->id) {
            abort(404);
        }

        if (! in_array($status, [
            Contribution::STATUS_PAID,
            Contribution::STATUS_WAIVED,
            Contribution::STATUS_VOID,
        ], true)) {
            $this->fail('Unsupported contribution status.');
        }

        return DB::transaction(function () use ($trip, $contribution, $status, $actor, $note) {
            $fare = TripFare::query()->where('trip_id', $trip->id)->lockForUpdate()->firstOrFail();

            if ($fare->status === TripFare::STATUS_SETTLED) {
                $this->fail('This fare is already settled.');
            }

            $contribution->update([
                'status' => $status,
                'settled_at' => $status === Contribution::STATUS_PAID ? now() : null,
                'settled_by' => $actor->id,
                'note' => $note ?? $contribution->note,
            ]);

            $pending = $fare->contributions()
                ->where('status', Contribution::STATUS_PENDING)
                ->exists();

            if (! $pending && $fare->status === TripFare::STATUS_AGREED) {
                $fare->update([
                    'status' => TripFare::STATUS_SETTLED,
                    'settled_at' => now(),
                    'actual_total_poisha' => (int) $fare->estimated_total_poisha,
                ]);

                TripEvent::create([
                    'trip_id' => $trip->id,
                    'actor_id' => $actor->id,
                    'event' => 'fare_settled',
                    'meta' => ['fare_version' => $fare->fare_version],
                ]);

                $this->notifications->fareUpdated(
                    $trip,
                    'fare_settled',
                    'Cost share settled',
                    'All trip contributions have been settled.',
                    $actor->id,
                );
            }

            return $fare->fresh($this->fareRelations());
        });
    }

    private function fareRelations(): array
    {
        return [
            'items',
            'contributions.user',
            'collector',
            'adjustments.proposer',
            'adjustments.votes.user',
        ];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['fare' => $message]);
    }
}
