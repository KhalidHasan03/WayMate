<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Chat\Services\ConversationService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function __construct(private readonly ConversationService $chat) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
            ->with([
                'trip.rideRequest',
                'trip.rideOffer',
                'participants.user',
                'latestMessage.sender',
            ])
            ->latest('id')
            ->get();

        $conversations->each(fn (Conversation $conversation) => $this->decorate($conversation, $user));

        return ConversationResource::collection($conversations);
    }

    public function forTrip(Trip $trip): ConversationResource
    {
        Gate::authorize('view', $trip);

        $conversation = $this->chat->forTrip($trip);
        $conversation->wasRecentlyCreated = false;
        $conversation->load(['trip.rideRequest', 'trip.rideOffer', 'participants.user', 'latestMessage.sender']);
        $this->decorate($conversation, request()->user());

        return new ConversationResource($conversation);
    }

    public function messages(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        $user = $request->user();
        $this->chat->participant($conversation, $user);

        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($data['limit'] ?? 40);
        $query = $conversation->messages()->with('sender');

        if (! empty($data['after_id'])) {
            $messages = $query->where('id', '>', (int) $data['after_id'])
                ->orderBy('id')
                ->limit($limit)
                ->get();
        } else {
            $messages = $query->latest('id')->limit($limit)->get()->reverse()->values();
        }

        return MessageResource::collection($messages);
    }

    public function send(Request $request, Conversation $conversation): MessageResource
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = $this->chat->send($conversation, $request->user(), $data['body']);

        return new MessageResource($message);
    }

    public function read(Request $request, Conversation $conversation): ConversationResource
    {
        $data = $request->validate([
            'last_message_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->chat->markRead(
            $conversation,
            $request->user(),
            isset($data['last_message_id']) ? (int) $data['last_message_id'] : null,
        );

        $conversation->load(['trip.rideRequest', 'trip.rideOffer', 'participants.user', 'latestMessage.sender']);
        $this->decorate($conversation, $request->user());

        return new ConversationResource($conversation);
    }

    private function decorate(Conversation $conversation, User $user): void
    {
        $participant = $conversation->participants->firstWhere('user_id', $user->id)
            ?? $conversation->participants()->where('user_id', $user->id)->first();

        $conversation->unread_count = $this->chat->unreadCount($conversation, $user);
        $conversation->last_read_message_id = $participant?->last_read_message_id;
    }
}
