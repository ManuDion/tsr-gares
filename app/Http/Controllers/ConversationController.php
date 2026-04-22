<?php

namespace App\Http\Controllers;

use App\Enums\ServiceModule;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Conversation::class);

        $this->pruneInactiveConversations();

        $user = $request->user();
        $this->ensureGeneralConversationAccess($user);
        $this->ensureServiceConversationsAccess($user);

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

        return view('chat.index', [
            'conversations' => $conversations,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Conversation::class);

        $user = $request->user();

        return view('chat.create', [
            'conversationTypeOptions' => [
                ['value' => 'general', 'label' => 'General'],
                ['value' => 'service_internal', 'label' => 'Interne service'],
                ['value' => 'direct', 'label' => 'Direct'],
            ],
            'serviceOptions' => $this->serviceOptionsForUser($user),
            'directUsers' => User::query()
                ->where('is_active', true)
                ->whereKeyNot($user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'department_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Conversation::class);

        $data = $request->validate([
            'conversation_type' => ['required', Rule::in(['general', 'service_internal', 'direct'])],
            'service_module' => ['nullable', Rule::in(array_map(fn (ServiceModule $module) => $module->value, ServiceModule::cases()))],
            'direct_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'initial_message' => ['nullable', 'string', 'max:4000'],
        ]);

        $user = $request->user();
        $type = (string) $data['conversation_type'];

        if ($type === 'general') {
            $conversation = Conversation::query()->where('conversation_type', 'general')->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'name' => 'Canal general TSR',
                    'is_group' => true,
                    'conversation_type' => 'general',
                    'service_module' => null,
                    'created_by' => $user->id,
                    'last_message_at' => null,
                ]);
            }

            $participantIds = User::query()->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->syncParticipants($conversation, $participantIds, $user->id);

            return $this->redirectWithMessage($conversation, $data['initial_message'] ?? null, $user, 'Canal general pret.');
        }

        if ($type === 'service_internal') {
            $module = ServiceModule::tryFrom((string) ($data['service_module'] ?? ''));
            if (! $module) {
                return back()->withErrors(['service_module' => 'Veuillez selectionner un service.'])->withInput();
            }

            if (! $user->canAccessModule($module)) {
                return back()->withErrors(['service_module' => 'Vous ne pouvez pas ouvrir une conversation pour ce service.'])->withInput();
            }

            $conversation = Conversation::query()
                ->whereIn('conversation_type', ['service_internal', 'inter_service'])
                ->where('service_module', $module->value)
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'name' => 'Canal service : '.$module->shortLabel(),
                    'is_group' => true,
                    'conversation_type' => 'service_internal',
                    'service_module' => $module->value,
                    'created_by' => $user->id,
                    'last_message_at' => null,
                ]);
            } elseif ($conversation->conversation_type !== 'service_internal') {
                $conversation->update(['conversation_type' => 'service_internal']);
            }

            $participantIds = $this->resolveServiceUserIds($module);
            if (! in_array($user->id, $participantIds, true)) {
                $participantIds[] = $user->id;
            }

            $this->syncParticipants($conversation, $participantIds, $user->id);

            return $this->redirectWithMessage($conversation, $data['initial_message'] ?? null, $user, 'Canal interne du service pret.');
        }

        $targetUserId = (int) ($data['direct_user_id'] ?? 0);
        if (! $targetUserId || $targetUserId === $user->id) {
            return back()->withErrors(['direct_user_id' => 'Veuillez choisir un interlocuteur valide.'])->withInput();
        }

        $existing = Conversation::query()
            ->where('conversation_type', 'direct')
            ->whereHas('participants', fn ($q) => $q->whereIn('users.id', [$user->id, $targetUserId]), '=', 2)
            ->withCount('participants')
            ->get()
            ->first(fn ($conversation) => $conversation->participants_count === 2);

        if ($existing) {
            return $this->redirectWithMessage($existing, $data['initial_message'] ?? null, $user, 'Conversation directe ouverte.');
        }

        $conversation = Conversation::create([
            'name' => null,
            'is_group' => false,
            'conversation_type' => 'direct',
            'service_module' => null,
            'created_by' => $user->id,
            'last_message_at' => null,
        ]);

        $this->syncParticipants($conversation, [$user->id, $targetUserId], $user->id);

        return $this->redirectWithMessage($conversation, $data['initial_message'] ?? null, $user, 'Conversation directe creee.');
    }

    public function show(Request $request, Conversation $conversation): View
    {
        if ($conversation->conversation_type === 'general') {
            $this->ensureGeneralConversationAccess($request->user());
        }

        if (in_array($conversation->conversation_type, ['service_internal', 'inter_service'], true)) {
            $this->ensureServiceConversationAccess($request->user(), $conversation);
        }

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

        return back()->with('status', 'Message envoye.');
    }

    public function pruneInactiveConversations(): int
    {
        return Conversation::query()
            ->where('conversation_type', 'direct')
            ->whereRaw('COALESCE(last_message_at, created_at) <= ?', [now()->subWeeks(2)->format('Y-m-d H:i:s')])
            ->delete();
    }

    protected function ensureGeneralConversationAccess(User $user): void
    {
        Conversation::query()
            ->where('conversation_type', 'general')
            ->get()
            ->each(function (Conversation $conversation) use ($user) {
                $conversation->participants()->syncWithoutDetaching([
                    $user->id => ['last_read_at' => null],
                ]);
            });
    }

    protected function ensureServiceConversationsAccess(User $user): void
    {
        Conversation::query()
            ->whereIn('conversation_type', ['service_internal', 'inter_service'])
            ->whereNotNull('service_module')
            ->get()
            ->each(function (Conversation $conversation) use ($user) {
                $this->ensureServiceConversationAccess($user, $conversation);
            });
    }

    protected function ensureServiceConversationAccess(User $user, Conversation $conversation): void
    {
        $module = ServiceModule::tryFrom((string) $conversation->service_module);
        if (! $module) {
            return;
        }

        if (! $user->canAccessModule($module)) {
            return;
        }

        $conversation->participants()->syncWithoutDetaching([
            $user->id => ['last_read_at' => null],
        ]);
    }

    protected function syncParticipants(Conversation $conversation, array $participantIds, int $initiatorId): void
    {
        $conversation->participants()->sync(
            collect($participantIds)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->mapWithKeys(fn ($id) => [$id => ['last_read_at' => $id === $initiatorId ? now() : null]])
                ->all()
        );
    }

    protected function redirectWithMessage(Conversation $conversation, ?string $initialMessage, User $user, string $status): RedirectResponse
    {
        if (! empty($initialMessage)) {
            $conversation->messages()->create([
                'user_id' => $user->id,
                'content' => $initialMessage,
            ]);

            $conversation->update(['last_message_at' => now()]);
            $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
        }

        return redirect()->route('chat.show', [
            'conversation' => $conversation,
            'module' => request('module'),
        ])->with('status', $status);
    }

    protected function serviceOptionsForUser(User $user): array
    {
        return collect(ServiceModule::cases())
            ->filter(fn (ServiceModule $module) => $user->canAccessModule($module))
            ->map(fn (ServiceModule $module) => [
                'value' => $module->value,
                'label' => $module->label(),
                'short_label' => $module->shortLabel(),
            ])
            ->values()
            ->all();
    }

    protected function resolveServiceUserIds(ServiceModule $module): array
    {
        return User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->assignedModule() === $module)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
