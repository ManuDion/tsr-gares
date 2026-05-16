@extends('layouts.app')

@section('title', 'Modifier recette')
@section('heading', ($module?->value ?? 'gares') === 'courrier' ? 'Modifier une recette courrier' : 'Modifier une recette')
@section('subheading', 'Historique et contrôle de la règle 48 heures')

@section('actions')
    @if(auth()->user()->canUnlockFinancialScope($recette->service_scope))
        <form method="POST" action="{{ route('recettes.unlock', ['recette' => $recette, 'module' => $module->value]) }}" class="form-inline unlock-controls">
            @csrf
            <input type="hidden" name="unlock_reason" value="Deverrouillage manuel par superviseur">
            <input type="number" name="unlock_duration" min="1" step="1" value="{{ old('unlock_duration', 24) }}" class="unlock-duration" required>
            <select name="unlock_unit" class="unlock-unit" required>
                <option value="minutes" @selected(old('unlock_unit') === 'minutes')>Minutes</option>
                <option value="hours" @selected(old('unlock_unit', 'hours') === 'hours')>Heures</option>
                <option value="days" @selected(old('unlock_unit') === 'days')>Jours</option>
            </select>
            <button class="btn btn-outline" type="submit">Deverrouiller</button>
        </form>
    @endif
@endsection

@section('content')
    <div class="grid-2">
        <div class="panel">
            <form method="POST" action="{{ route('recettes.update', ['recette' => $recette, 'module' => $module->value]) }}" enctype="multipart/form-data" class="stack-md">
                @csrf
                @method('PUT')
                <input type="hidden" name="module" value="{{ $module->value }}">
                @include('recettes._form', ['recette' => $recette])
                <div class="form-actions">
                    <a class="btn btn-outline" href="{{ route('recettes.index', ['module' => $module->value]) }}">Retour</a>
                    <button class="btn btn-primary" type="submit">Mettre à jour</button>
                </div>
            </form>

            @if($recette->justificatives->isNotEmpty())
                <div class="doc-links doc-links-top">
                    @foreach($recette->justificatives as $piece)
                        <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.preview', $piece) }}" data-internal-file-preview data-file-title="{{ $piece->original_name ?? 'Justificatif recette' }}" onclick="return window.openInternalFileViewer(this);">
                            <span class="icon">{!! app_icon('eye') !!}</span> Lire le justificatif
                        </a>
                        @if(auth()->user()->hasGlobalVisibility())
                            <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.download', $piece) }}">
                                <span class="icon">{!! app_icon('download') !!}</span> Télécharger
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        <div class="panel">
            <h2>Historique des modifications</h2>
            @php
                $filteredHistories = $recette->histories->filter(function ($history) {
                    return collect($history->before ?? [])->keys()->contains(function ($key) use ($history) {
                        return (string) data_get($history->before, $key) !== (string) data_get($history->after, $key);
                    });
                });
            @endphp
            <div class="timeline">
                @forelse($filteredHistories as $history)
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

