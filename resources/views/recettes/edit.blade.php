@extends('layouts.app')

@section('title', 'Modifier recette')
@section('heading', 'Modifier une recette')
@section('subheading', 'Historique et contrôle de la règle 48 heures')

@section('actions')
    @if(auth()->user()->isAdmin() || auth()->user()->isResponsable())
        <form method="POST" action="{{ route('recettes.unlock', $recette) }}">
            @csrf
            <input type="hidden" name="unlock_reason" value="Déverrouillage manuel par superviseur">
            <button class="btn btn-outline" type="submit">Déverrouiller 24h</button>
        </form>
    @endif
@endsection

@section('content')
    <div class="grid-2">
        <div class="panel">
            <form method="POST" action="{{ route('recettes.update', $recette) }}" class="stack-md">
                @csrf
                @method('PUT')
                @include('recettes._form', ['recette' => $recette])
                <div class="form-actions">
                    <a class="btn btn-outline" href="{{ route('recettes.index') }}">Retour</a>
                    <button class="btn btn-primary" type="submit">Mettre à jour</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h2>Historique des modifications</h2>
            <div class="timeline">
                @forelse($recette->histories as $history)
                    <div class="timeline-item">
                        <strong>{{ $history->modifier->name ?? 'Système' }}</strong>
                        <small>{{ $history->created_at?->format('d/m/Y H:i') }}</small>
                        <p>{{ $history->comment ?: 'Modification' }}</p>
                    </div>
                @empty
                    <p>Aucune modification enregistrée.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
