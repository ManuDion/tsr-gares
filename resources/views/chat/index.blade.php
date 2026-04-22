@extends('layouts.app')

@section('title', 'Chat')
@section('heading', 'Messagerie interne')
@section('subheading', 'Conversations generales, internes service, ou directes')

@section('actions')
    <div class="button-row">
        <a class="btn btn-primary" href="{{ route('chat.create', ['module' => request('module')]) }}">
            <span class="icon">{!! app_icon('plus') !!}</span> Nouvelle conversation
        </a>
    </div>
@endsection

@section('content')
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2>Mes conversations</h2>
                <p class="text-muted">Cliquez sur une conversation pour lire et repondre aux messages.</p>
            </div>
        </div>

        <div class="chat-list">
            @forelse($conversations as $conversation)
                @php($latest = $conversation->messages->first())
                <a class="list-card" href="{{ route('chat.show', ['conversation' => $conversation, 'module' => request('module')]) }}">
                    <div class="list-card-header">
                        <h3>{{ $conversation->displayNameFor(auth()->user()) }}</h3>
                        @if(($conversation->unread_count ?? 0) > 0)
                            <span class="nav-badge">{{ $conversation->unread_count }}</span>
                        @endif
                    </div>
                    <div class="text-muted">
                        {{ $conversation->typeLabel() }}
                        - {{ ($conversation->last_message_at ?: $conversation->created_at)?->format('d/m/Y H:i') ?? 'Sans message' }}
                    </div>
                    <p>{{ \Illuminate\Support\Str::limit($latest?->content ?? 'Aucun message pour le moment.', 120) }}</p>
                </a>
            @empty
                <div class="empty-state">
                    <strong>Aucune conversation</strong>
                    <p>Creez une conversation generale, interne service ou directe.</p>
                </div>
            @endforelse
        </div>

        {{ $conversations->links('partials.pagination') }}
    </div>
@endsection
