@extends('layouts.app')

@section('title', 'Conversation')
@section('heading', $conversation->displayNameFor(auth()->user()))
@section('subheading', $conversation->is_group ? 'Discussion de groupe' : 'Conversation privée')

@section('actions')
    <a class="btn btn-outline" href="{{ route('chat.index') }}">
        <span class="icon">{!! app_icon('back') !!}</span> Retour au chat
    </a>
@endsection

@section('content')
    <div class="chat-thread">
        <div class="panel">
            <h2>Participants</h2>
            <p>{{ $conversation->participants->pluck('name')->implode(', ') }}</p>
        </div>

        <div class="panel">
            <h2>Messages</h2>
            <div class="list-stack">
                @forelse($conversation->messages as $message)
                    <div class="chat-bubble {{ $message->user_id === auth()->id() ? 'me' : '' }}">
                        <div class="chat-meta">
                            <strong>{{ $message->user?->name ?? 'Utilisateur' }}</strong>
                            <span>{{ $message->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        <div>{{ $message->content }}</div>
                    </div>
                @empty
                    <div class="empty-state">
                        <strong>Aucun message</strong>
                        <p>Envoyez le premier message à partir du formulaire ci-dessous.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <h2>Nouveau message</h2>
            <form method="POST" action="{{ route('chat.messages.store', $conversation) }}" class="stack-md">
                @csrf
                <div>
                    <label>Message</label>
                    <textarea class="message-box" name="content" rows="5" required></textarea>
                </div>
                <button class="btn btn-primary" type="submit">
                    <span class="icon">{!! app_icon('chat') !!}</span> Envoyer
                </button>
            </form>
        </div>
    </div>
@endsection
