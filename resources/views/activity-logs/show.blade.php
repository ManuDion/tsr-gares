@extends('layouts.app')

@section('title', 'Détail de modification')
@section('heading', 'Détail de la modification')
@section('subheading', 'Comparaison des valeurs avant et après la mise à jour')

@section('actions')
    <a class="btn btn-outline" href="{{ route('activity-logs.index', request()->query()) }}">
        <span class="icon">{!! app_icon('back') !!}</span> Retour à l'historique
    </a>
@endsection

@section('content')
    <div class="grid-2">
        <div class="panel">
            <h2>Résumé</h2>
            <dl class="definition-list">
                <div><dt>Objet</dt><dd>{{ $log->subject }}</dd></div>
                <div><dt>Utilisateur</dt><dd>{{ $log->user?->name ?? 'Système' }}</dd></div>
                <div><dt>Date et heure</dt><dd>{{ $log->created_at?->format('d/m/Y H:i') }}</dd></div>
                <div><dt>Événement</dt><dd>{{ $eventLabels[$log->event_type] ?? \Illuminate\Support\Str::headline($log->event_type) }}</dd></div>
                <div><dt>Gare</dt><dd>{{ $log->gare?->name ?: 'Toutes gares' }}</dd></div>
                <div><dt>Description</dt><dd>{{ $log->description ?: 'Modification enregistrée.' }}</dd></div>
            </dl>
        </div>

        <div class="panel">
            <h2>Données techniques</h2>
            <dl class="definition-list">
                <div><dt>Entité</dt><dd>{{ $log->entity_type ?: 'Système' }}</dd></div>
                <div><dt>Identifiant entité</dt><dd>{{ $log->entity_id ?: '—' }}</dd></div>
                <div><dt>Adresse IP</dt><dd>{{ data_get($log->meta, 'ip', '—') }}</dd></div>
                <div><dt>URL</dt><dd class="break-all">{{ data_get($log->meta, 'url', '—') }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="grid-2">
        <div class="panel">
            <h2>Valeurs avant modification</h2>
            <ul class="change-list">
                @forelse(($log->before ?? []) as $key => $value)
                    <li><strong>{{ \Illuminate\Support\Str::headline($key) }} :</strong> {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : ($value === null || $value === '' ? '—' : $value) }}</li>
                @empty
                    <li>Aucune valeur précédente.</li>
                @endforelse
            </ul>
        </div>

        <div class="panel">
            <h2>Valeurs après modification</h2>
            <ul class="change-list">
                @forelse(($log->after ?? []) as $key => $value)
                    <li><strong>{{ \Illuminate\Support\Str::headline($key) }} :</strong> {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : ($value === null || $value === '' ? '—' : $value) }}</li>
                @empty
                    <li>Aucune valeur nouvelle.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
