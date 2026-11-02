<?php

namespace App\Domains\Notifications\Services;

use App\Models\Conversation;
use App\Models\JoinRequest;
use App\Models\Message;
use App\Models\RideOffer;
use App\Models\RideRequest;
use App\Models\Trip;
use App\Models\TripParticipant;
use App\Models\User;
use App\Notifications\TripActivityNotification;
use Illuminate\Support\Collection;

class NotificationService
{
    public function send(
        User $user,
        string $type,
        string $title,
        string $body,
        ?string $url = null,
        array $meta = [],
    ): void {
        if (! $user->isActive()) {
            return;
        }

        $user->notify(new TripActivityNotification([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'meta' => $meta,
        ]));
    }

    public function joinRequested(JoinRequest $join): void
    {
        $target = $join->ride_offer_id !== null
            ? RideOffer::query()->with('provider')->find($join->ride_offer_id)?->provider
            : RideRequest::query()->with('creator')->find($join->ride_request_id)?->creator;

        if (! $target) {
            return;
        }

        $actor = $join->user?->name ?? 'Someone';

        $this->send(
            $target,
            'join_request',
            'New join request',
            $join->role === JoinRequest::ROLE_PROVIDER
                ? "{$actor} offered to drive your ride request."
                : "{$actor} asked to join your ride.",
            '/app/trips',
            ['join_request_id' => $join->id],
        );
    }

    public function joinDecided(JoinRequest $join, bool $accepted): void
    {
        $user = $join->user;

        if (! $user) {
            return;
        }

        $this->send(
            $user,
            $accepted ? 'join_accepted' : 'join_declined',
            $accepted ? 'Join request accepted' : 'Join request declined',
            $accepted
                ? 'You are in. Open your trips to see the shared ride and group chat.'
                : 'Your join request was declined.',
            '/app/trips',
            ['join_request_id' => $join->id],
        );
    }

    public function tripLifecycle(Trip $trip, string $type, string $title, string $body, ?int $exceptUserId = null): void
    {
        $this->notifyParticipants($trip, $type, $title, $body, '/app/trips', $exceptUserId);
    }

    public function fareUpdated(
        Trip $trip,
        string $type,
        string $title,
        string $body,
        ?int $exceptUserId = null,
    ): void {
        $this->notifyParticipants($trip, $type, $title, $body, '/app/trips', $exceptUserId);
    }

    public function newMessage(Conversation $conversation, Message $message): void
    {
        $recipients = $conversation->participants()
            ->where('user_id', '!=', $message->sender_id)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        $senderName = $message->sender?->name ?? 'A trip member';

        foreach ($recipients as $user) {
            $this->send(
                $user,
                'chat_message',
                "New message from {$senderName}",
                mb_strimwidth($message->body, 0, 120, '…'),
                "/app/chat/{$conversation->trip_id}",
                ['conversation_id' => $conversation->id, 'message_id' => $message->id],
            );
        }
    }

    private function notifyParticipants(
        Trip $trip,
        string $type,
        string $title,
        string $body,
        ?string $url,
        ?int $exceptUserId,
    ): void {
        $users = $trip->participants()
            ->whereIn('status', [
                TripParticipant::STATUS_CONFIRMED,
                TripParticipant::STATUS_CHECKED_IN,
                TripParticipant::STATUS_COMPLETED,
            ])
            ->when($exceptUserId, fn ($query) => $query->where('user_id', '!=', $exceptUserId))
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id');

        $this->sendMany($users, $type, $title, $body, $url, ['trip_id' => $trip->id]);
    }

    private function sendMany(
        Collection $users,
        string $type,
        string $title,
        string $body,
        ?string $url,
        array $meta,
    ): void {
        foreach ($users as $user) {
            $this->send($user, $type, $title, $body, $url, $meta);
        }
    }
}
