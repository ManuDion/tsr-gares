@extends('layouts.app')

@section('title', 'Modifier dépense')
@section('heading', 'Modifier une dépense')
@section('subheading', 'Historique et contrôle de la règle 48 heures')

@section('actions')
    @if(auth()->user()->isAdmin() || auth()->user()->isResponsable())
        <form method="POST" action="{{ route('depenses.unlock', $depense) }}">
            @csrf
            <input type="hidden" name="unlock_reason" value="Déverrouillage manuel par superviseur">
            <button class="btn btn-outline" type="submit">Déverrouiller 24h</button>
        </form>
    @endif
@endsection

@section('content')
    <div class="grid-2">
        <div class="panel">
            <form method="POST" action="{{ route('depenses.update', $depense) }}" enctype="multipart/form-data" class="stack-md">
                @csrf
                @method('PUT')
                @include('depenses._form', ['depense' => $depense])
                <div class="form-actions">
                    <a class="btn btn-outline" href="{{ route('depenses.index') }}">Retour</a>
                    <button class="btn btn-primary" type="submit">Mettre à jour</button>
                </div>
            </form>

            @if($depense->justificatives->isNotEmpty())
                <div class="doc-links" style="margin-top: 1rem;">
                    @foreach($depense->justificatives as $piece)
                        <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.preview', $piece) }}" target="_blank">
                            <span class="icon">{!! app_icon('eye') !!}</span> Lire le justificatif
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
                @forelse($depense->histories as $history)
                    <div class="timeline-item timeline-item-detailed">
                        <strong>{{ $history->modifier->name ?? 'Système' }}</strong>
                        <small>{{ $history->created_at?->format('d/m/Y H:i') }}</small>
                        <p>{{ $history->comment ?: 'Modification' }}</p>
                        <div class="history-diff-grid">
                            <div>
                                <h3>Avant</h3>
                                <ul class="change-list">
                                    @foreach($history->before ?? [] as $key => $value)
                                        <li><strong>{{ \Illuminate\Support\Str::headline($key) }} :</strong> {{ $value === null || $value === '' ? '—' : $value }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <div>
                                <h3>Après</h3>
                                <ul class="change-list">
                                    @foreach($history->after ?? [] as $key => $value)
                                        <li><strong>{{ \Illuminate\Support\Str::headline($key) }} :</strong> {{ $value === null || $value === '' ? '—' : $value }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @empty
                    <p>Aucune modification enregistrée.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
