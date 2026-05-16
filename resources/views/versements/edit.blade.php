@extends('layouts.app')

@section('title', 'Modifier versement')
@section('heading', ($module?->value ?? 'gares') === 'courrier' ? 'Modifier un versement courrier' : 'Modifier un versement bancaire')
@section('subheading', 'Même principe de verrouillage que les recettes')

@section('actions')
    @if(auth()->user()->canUnlockFinancialScope($versement->service_scope))
        <form method="POST" action="{{ route('versements.unlock', ['versement' => $versement, 'module' => $module->value]) }}" class="form-inline unlock-controls">
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
            <form method="POST" action="{{ route('versements.update', ['versement' => $versement, 'module' => $module->value]) }}" enctype="multipart/form-data" class="stack-md">
                @csrf
                @method('PUT')
                <input type="hidden" name="module" value="{{ $module->value }}">
                @include('versements._form', ['maxSizeKb' => $maxSizeKb])

                <div class="form-actions">
                    <a class="btn btn-outline" href="{{ route('versements.index', ['module' => $module->value]) }}">Retour</a>
                    <button class="btn btn-primary" type="submit">Mettre à jour</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h2>Historique des modifications</h2>
            @php
                $filteredHistories = $versement->histories->filter(function ($history) {
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
                        <p>{{ $history->comment ?: 'Modification de versement' }}</p>
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

