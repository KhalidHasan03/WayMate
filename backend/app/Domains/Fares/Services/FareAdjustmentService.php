<?php

namespace App\Domains\Fares\Services;

use App\Domains\Notifications\Services\NotificationService;
use App\Models\Contribution;
use App\Models\FareAdjustment;
use App\Models\FareItem;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\TripFare;
use App\Models\TripParticipant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FareAdjustmentService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function propose(Trip $trip, User $user, int $newTotal, ?string $reason): TripFare
    {
        return DB::transaction(function () use ($trip, $user, $newTotal, $reason) {
            $fare = TripFare::query()->where('trip_id', $trip->id)->lockForUpdate()->first();

            if (! $fare || $fare->status !== TripFare::STATUS_AGREED) {
                $this->fail('The fare must be agreed before an adjustment can be proposed.');
            }

            if ((int) $fare->estimated_total_poisha === $newTotal) {
                $this->fail('The proposed total is the same as the current fare.');
            }

            if (! $this->participants($trip)->contains('user_id', $user->id)) {
                $this->fail('Only confirmed participants can propose a fare adjustment.');
            }

            $nextVersion = max(
                (int) $fare->fare_version,
                (int) $fare->adjustments()->max('fare_version'),
            ) + 1;

            if ($nextVersion > (int) config('waymate.fare.max_fare_version', 3)) {
                $this->fail('This fare has reached its maximum number of versions.');
            }

            $adjustment = FareAdjustment::create([
                'trip_fare_id' => $fare->id,
                'fare_version' => $nextVersion,
                'proposed_by' => $user->id,
                'new_total_poisha' => $newTotal,
                'reason' => $reason,
                'status' => FareAdjustment::STATUS_PENDING,
            ]);

            $fare->update(['status' => TripFare::STATUS_ADJUSTMENT_PENDING]);

            $eligible = $this->participants($trip)->where('user_id', '!=', $user->id);

            if ($eligible->isNotEmpty()) {
                $this->notifications->fareUpdated(
                    $trip,
                    'fare_adjustment_proposed',
                    'Fare change proposed',
                    'A new trip cost total of '.number_format($newTotal / 100, 2).' Tk was proposed. Vote in trips.',
                    $user->id,
                );
            }

            if ($eligible->isEmpty()) {
                $this->apply($trip, $fare, $adjustment, $user);
            }

            return $fare->fresh($this->relations());
        });
    }

    public function vote(Trip $trip, FareAdjustment $adjustment, User $user, string $vote): TripFare
    {
        if ((int) $adjustment->tripFare->trip_id !== (int) $trip->id) {
            abort(404);
        }

        if (! in_array($vote, ['accept', 'reject'], true)) {
            $this->fail('Unsupported vote.');
        }

        return DB::transaction(function () use ($trip, $adjustment, $user, $vote) {
            $fare = TripFare::query()->where('trip_id', $trip->id)->lockForUpdate()->firstOrFail();
            $adjustment = FareAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if ($fare->status !== TripFare::STATUS_ADJUSTMENT_PENDING
                || $adjustment->status !== FareAdjustment::STATUS_PENDING) {
                $this->fail('This adjustment is no longer open for voting.');
            }

            $participants = $this->participants($trip);

            if (! $participants->contains('user_id', $user->id)) {
                $this->fail('Only confirmed participants can vote on a fare adjustment.');
            }

            if ((int) $adjustment->proposed_by === (int) $user->id) {
                $this->fail('The proposer cannot vote on their own adjustment.');
            }

            $alreadyVoted = DB::table('fare_adjustment_votes')
                ->where('adjustment_id', $adjustment->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($alreadyVoted) {
                $this->fail('You have already voted on this adjustment.');
            }

            DB::table('fare_adjustment_votes')->insert([
                'adjustment_id' => $adjustment->id,
                'user_id' => $user->id,
                'vote' => $vote,
                'voted_at' => now(),
            ]);

            $eligible = $participants->where('user_id', '!=', $adjustment->proposed_by);
            $ratio = (float) config('waymate.fare.adjustment_approval_ratio', 0.6);
            $required = max(1, (int) ceil($eligible->count() * $ratio));

            $accepts = DB::table('fare_adjustment_votes')
                ->where('adjustment_id', $adjustment->id)
                ->where('vote', 'accept')
                ->count();
            $rejects = DB::table('fare_adjustment_votes')
                ->where('adjustment_id', $adjustment->id)
                ->where('vote', 'reject')
                ->count();

            if ($accepts >= $required) {
                $this->apply($trip, $fare, $adjustment, $user);
            } elseif ($rejects > $eligible->count() - $required) {
                $adjustment->update([
                    'status' => FareAdjustment::STATUS_REJECTED,
                    'resolved_at' => now(),
                ]);
                $fare->update(['status' => TripFare::STATUS_AGREED]);

                TripEvent::create([
                    'trip_id' => $trip->id,
                    'actor_id' => $user->id,
                    'event' => 'fare_adjustment_rejected',
                    'meta' => ['adjustment_id' => $adjustment->id],
                ]);

                $this->notifications->fareUpdated(
                    $trip,
                    'fare_adjustment_rejected',
                    'Fare change rejected',
                    'The proposed trip cost change was rejected. The current fare stands.',
                    $user->id,
                );
            }

            return $fare->fresh($this->relations());
        });
    }

    private function apply(
        Trip $trip,
        TripFare $fare,
        FareAdjustment $adjustment,
        User $actor,
    ): void {
        $newTotal = (int) $adjustment->new_total_poisha;
        $providerOnly = (int) $fare->items()
            ->where('split_method', FareItem::SPLIT_PROVIDER_ONLY)
            ->sum('amount_poisha');
        $targetShared = $newTotal - $providerOnly;

        if ($targetShared < 0) {
            $this->fail('The proposed total is below the costs assigned to the driver.');
        }

        $equalItems = $fare->items()
            ->where('split_method', FareItem::SPLIT_EQUAL)
            ->orderBy('id')
            ->get();
        $oldShared = (int) $equalItems->sum('amount_poisha');
        $remaining = $targetShared;

        foreach ($equalItems as $index => $item) {
            $isLast = $index === $equalItems->count() - 1;
            $amount = $isLast
                ? $remaining
                : ($oldShared > 0
                    ? intdiv((int) $item->amount_poisha * $targetShared, $oldShared)
                    : 0);

            $item->update(['amount_poisha' => $amount]);
            $remaining -= $amount;
        }

        if ($equalItems->isEmpty()) {
            FareItem::create([
                'trip_fare_id' => $fare->id,
                'label' => 'Adjusted share',
                'amount_poisha' => $targetShared,
                'split_method' => FareItem::SPLIT_EQUAL,
                'created_by' => $actor->id,
            ]);
        }

        $fare->contributions()->where('status', Contribution::STATUS_PENDING)->delete();

        $version = (int) $adjustment->fare_version;
        $participants = $this->participants($trip);
        $units = [];

        foreach ($participants as $participant) {
            $units[$participant->user_id] = $participant->role === TripParticipant::ROLE_PROVIDER
                ? ($fare->provider_participates ? 1 : 0)
                : max(1, (int) $participant->seats);
        }

        $totalUnits = array_sum($units);

        if ($totalUnits < 1) {
            $this->fail('At least one participant must share the cost.');
        }

        $base = intdiv($targetShared, $totalUnits);
        $remainder = $targetShared % $totalUnits;
        $amounts = [];

        foreach ($units as $userId => $userUnits) {
            $amounts[$userId] = $base * $userUnits;
        }

        $collectorId = $fare->collector_id ?? $trip->provider_id ?? $trip->coordinator_id;

        if ($remainder > 0) {
            $receiver = $collectorId !== null && array_key_exists($collectorId, $units)
                ? $collectorId
                : array_key_first($units);
            $amounts[$receiver] += $remainder;
        }

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
                'note' => "Adjusted fare share v{$version}",
            ]);
        }

        $fare->update([
            'estimated_total_poisha' => $newTotal,
            'agreed_total_poisha' => $newTotal,
            'participant_count' => count(array_filter($units)),
            'rounding_absorbed_poisha' => $remainder,
            'fare_version' => $version,
            'status' => TripFare::STATUS_AGREED,
            'agreed_at' => now(),
            'settled_at' => null,
        ]);

        $participants->each(fn ($participant) => $participant->update([
            'consented_fare_version' => $version,
        ]));

        $adjustment->update([
            'status' => FareAdjustment::STATUS_ACCEPTED,
            'resolved_at' => now(),
        ]);

        TripEvent::create([
            'trip_id' => $trip->id,
            'actor_id' => $actor->id,
            'event' => 'fare_adjustment_accepted',
            'meta' => ['adjustment_id' => $adjustment->id, 'total_poisha' => $newTotal],
        ]);

        $this->notifications->fareUpdated(
            $trip,
            'fare_adjustment_accepted',
            'Fare change accepted',
            'The new trip cost total is '.number_format($newTotal / 100, 2).' Tk. Review your updated share.',
            $actor->id,
        );
    }

    private function participants(Trip $trip): Collection
    {
        return $trip->participants()
            ->whereIn('status', [
                TripParticipant::STATUS_CONFIRMED,
                TripParticipant::STATUS_CHECKED_IN,
                TripParticipant::STATUS_COMPLETED,
            ])
            ->get();
    }

    private function relations(): array
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
