<?php

namespace App\Domains\Chat\Services;

use App\Domains\Notifications\Services\NotificationService;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Trip;
use App\Models\TripParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function forTrip(Trip $trip): Conversation
    {
        return DB::transaction(function () use ($trip) {
            $conversation = Conversation::query()->firstOrCreate(['trip_id' => $trip->id]);

            $userIds = $trip->participants()
                ->whereIn('status', [
                    TripParticipant::STATUS_CONFIRMED,
                    TripParticipant::STATUS_CHECKED_IN,
                    TripParticipant::STATUS_COMPLETED,
                ])
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                DB::table('conversation_participants')->insertOrIgnore([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'joined_at' => now(),
                ]);
            }

            return $conversation->load(['trip', 'participants.user']);
        });
    }

    public function participant(Conversation $conversation, User $user): ConversationParticipant
    {
        $participant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

        abort_if($participant === null, 403, 'You are not part of this conversation.');

        return $participant;
    }

    public function send(Conversation $conversation, User $user, string $body): Message
    {
        $participant = $this->participant($conversation, $user);
        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Write a message first.']);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'type' => Message::TYPE_TEXT,
            'body' => $body,
        ]);

        DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update([
                'last_read_message_id' => $message->id,
                'last_read_at' => now(),
            ]);

        $message->load('sender');
        $this->notifications->newMessage($conversation, $message);

        return $message;
    }

    public function markRead(Conversation $conversation, User $user, ?int $lastMessageId = null): ConversationParticipant
    {
        $participant = $this->participant($conversation, $user);

        $latestId = (int) $conversation->messages()->max('id');
        $target = $lastMessageId !== null
            ? min(max(0, $lastMessageId), $latestId)
            : $latestId;

        if ($target > (int) $participant->last_read_message_id) {
            DB::table('conversation_participants')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->update([
                    'last_read_message_id' => $target,
                    'last_read_at' => now(),
                ]);

            $participant = ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->first();
        }

        return $participant;
    }

    public function unreadCount(Conversation $conversation, User $user): int
    {
        $participant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant === null) {
            return 0;
        }

        return $conversation->messages()
            ->where('id', '>', (int) $participant->last_read_message_id)
            ->where('sender_id', '!=', $user->id)
            ->count();
    }

    public function system(Conversation $conversation, string $body): Message
    {
        $senderId = $conversation->trip?->coordinator_id
            ?? $conversation->participants()->value('user_id');

        if ($senderId === null) {
            return new Message;
        }

        return Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $senderId,
            'type' => Message::TYPE_SYSTEM,
            'body' => $body,
        ]);
    }
}
