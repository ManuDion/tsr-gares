@extends('layouts.app')

@section('title', 'Chat')
@section('heading', 'Messagerie interne')
@section('subheading', 'Conversations privées ou multi-participants entre utilisateurs')

@section('actions')
    <div class="button-row">
        <a class="btn btn-primary" href="{{ route('chat.index', ['new' => 1]) }}">
            <span class="icon">{!! app_icon('plus') !!}</span> Nouvelle conversation
        </a>
    </div>
@endsection

@section('content')
    <div class="stack-lg">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2>Mes conversations</h2>
                    <p class="text-muted">Cliquez sur une conversation pour lire et répondre aux messages.</p>
                </div>
            </div>

            <div class="chat-list">
                @forelse($conversations as $conversation)
                    @php($latest = $conversation->messages->first())
                    <a class="list-card" href="{{ route('chat.show', $conversation) }}">
                        <div class="list-card-header">
                            <h3>{{ $conversation->displayNameFor(auth()->user()) }}</h3>
                            @if(($conversation->unread_count ?? 0) > 0)
                                <span class="nav-badge">{{ $conversation->unread_count }}</span>
                            @endif
                        </div>
                        <div class="text-muted">
                            {{ $conversation->is_group ? 'Conversation multi-participants' : 'Conversation privée' }}
                            · {{ ($conversation->last_message_at ?: $conversation->created_at)?->format('d/m/Y H:i') ?? 'Sans message' }}
                        </div>
                        <p>{{ \Illuminate\Support\Str::limit($latest?->content ?? 'Aucun message pour le moment.', 120) }}</p>
                    </a>
                @empty
                    <div class="empty-state">
                        <strong>Aucune conversation</strong>
                        <p>Créez une conversation et choisissez un ou plusieurs participants pour démarrer les échanges.</p>
                    </div>
                @endforelse
            </div>

            {{ $conversations->links('partials.pagination') }}
        </div>

        @if($showComposer)
            <div class="panel">
                <h2>Nouvelle conversation</h2>
                <form method="POST" action="{{ route('chat.store') }}" class="stack-md">
                    @csrf

                    <div>
                        <label>Participants</label>
                        <div class="checkbox-grid">
                            @foreach($users as $item)
                                <label class="checkbox-card">
                                    <input type="checkbox" name="participant_ids[]" value="{{ $item->id }}" @checked(collect(old('participant_ids', []))->contains($item->id))>
                                    <span>
                                        <strong>{{ $item->name }}</strong>
                                        <small>{{ $item->roleLabel() }}</small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <small>Cochez une personne pour une conversation privée, ou plusieurs participants pour une conversation de groupe.</small>
                    </div>

                    <div>
                        <label>Premier message (optionnel)</label>
                        <textarea name="initial_message" rows="4">{{ old('initial_message') }}</textarea>
                    </div>

                    <div class="form-actions">
                        <a class="btn btn-outline" href="{{ route('chat.index') }}">Annuler</a>
                        <button class="btn btn-primary" type="submit">
                            <span class="icon">{!! app_icon('chat') !!}</span>
                            Créer la conversation
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
@endsection
