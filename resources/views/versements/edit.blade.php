@extends('layouts.app')

@section('title', 'Modifier versement')
@section('heading', 'Modifier un versement bancaire')
@section('subheading', 'Même principe de verrouillage que les recettes')

@section('actions')
    @if(auth()->user()->isAdmin() || auth()->user()->isResponsable())
        <form method="POST" action="{{ route('versements.unlock', $versement) }}">
            @csrf
            <input type="hidden" name="unlock_reason" value="Déverrouillage manuel par superviseur">
            <button class="btn btn-outline" type="submit">Déverrouiller 24h</button>
        </form>
    @endif
@endsection

@section('content')
    <div class="grid-2">
        <div class="panel">
            <form method="POST" action="{{ route('versements.update', $versement) }}" class="stack-md">
                @csrf
                @method('PUT')
                @include('versements._form', ['draft' => null, 'draftToken' => null, 'defaultGareLabel' => null])
                <div class="form-actions">
                    <a class="btn btn-outline" href="{{ route('versements.index') }}">Retour</a>
                    <button class="btn btn-primary" type="submit">Mettre à jour</button>
                </div>
            </form>

            @if($versement->justificatives->isNotEmpty())
                <div class="doc-links" style="margin-top: 1rem;">
                    @foreach($versement->justificatives as $piece)
                        <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.preview', $piece) }}" target="_blank">
                            <span class="icon">{!! app_icon('eye') !!}</span> Lire le bordereau
                        </a>
                        <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.download', $piece) }}">
                            <span class="icon">{!! app_icon('download') !!}</span> Télécharger
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="panel">
            <h2>Historique des modifications</h2>
            <div class="timeline">
                @forelse($versement->histories as $history)
                    <div class="timeline-item">
                        <strong>{{ $history->modifier->name ?? 'Système' }}</strong>
                        <small>{{ $history->created_at?->format('d/m/Y H:i') }}</small>
                        <p>{{ $history->comment ?: 'Modification de versement' }}</p>
                    </div>
                @empty
                    <p>Aucune modification enregistrée.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
