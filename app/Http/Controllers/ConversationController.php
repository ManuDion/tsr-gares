<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Conversation::class);

        $this->pruneInactiveConversations();

        $user = $request->user();
        $showComposer = $request->boolean('new');

        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
            ->with([
                'participants',
                'messages' => fn ($q) => $q->latest()->limit(1),
                'messages.user',
            ])
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->paginate(15)
            ->withQueryString();

        $conversations->getCollection()->transform(function (Conversation $conversation) use ($user) {
            $conversation->unread_count = $conversation->unreadMessagesCountFor($user);

            return $conversation;
        });

        $users = User::query()
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->orderBy('name')
            ->get();

        return view('chat.index', [
            'conversations' => $conversations,
            'users' => $users,
            'showComposer' => $showComposer,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Conversation::class);

        $data = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
            'initial_message' => ['nullable', 'string', 'max:4000'],
        ]);

        $user = $request->user();
        $participantIds = collect($data['participant_ids'])->map(fn ($id) => (int) $id)->unique()->values();

        if ($participantIds->count() === 1) {
            $targetId = $participantIds->first();

            $existing = Conversation::query()
                ->where('is_group', false)
                ->whereHas('participants', fn ($q) => $q->whereIn('users.id', [$user->id, $targetId]), '=', 2)
                ->withCount('participants')
                ->get()
                ->first(fn ($conversation) => $conversation->participants_count === 2);

            if ($existing) {
                return redirect()->route('chat.show', $existing)->with('status', 'Conversation déjà existante.');
            }
        }

        $isGroup = $participantIds->count() > 1;
        $participantNames = User::query()
            ->whereIn('id', $participantIds->all())
            ->orderBy('name')
            ->pluck('name')
            ->values();

        $conversation = Conversation::create([
            'name' => $isGroup ? 'Discussion : '.$participantNames->take(3)->implode(', ').($participantNames->count() > 3 ? '...' : '') : null,
            'is_group' => $isGroup,
            'created_by' => $user->id,
            'last_message_at' => ! empty($data['initial_message']) ? now() : null,
        ]);

        $conversation->participants()->sync(
            $participantIds->push($user->id)
                ->unique()
                ->mapWithKeys(fn ($id) => [$id => ['last_read_at' => $id === $user->id ? now() : null]])
                ->all()
        );

        if (! empty($data['initial_message'])) {
            $conversation->messages()->create([
                'user_id' => $user->id,
                'content' => $data['initial_message'],
            ]);
        }

        return redirect()->route('chat.show', $conversation)->with(
            'status',
            $isGroup ? 'Conversation de groupe créée.' : 'Conversation créée.'
        );
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorize('view', $conversation);

        $conversation->load(['participants', 'messages.user']);
        $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);

        return view('chat.show', [
            'conversation' => $conversation,
        ]);
    }

    public function storeMessage(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
        ]);

        $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);

        $conversation->update(['last_message_at' => now()]);
        $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);

        return back()->with('status', 'Message envoyé.');
    }

    public function pruneInactiveConversations(): int
    {
        return Conversation::query()
            ->whereRaw('COALESCE(last_message_at, created_at) <= ?', [now()->subWeeks(2)->format('Y-m-d H:i:s')])
            ->delete();
    }
}
