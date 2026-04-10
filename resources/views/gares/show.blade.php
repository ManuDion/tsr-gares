@extends('layouts.app')

@section('title', 'Fiche gare')
@section('heading', $gare->name)
@section('subheading', 'Vue détaillée de la gare')

@section('actions')
    <a class="btn btn-outline" href="{{ url()->previous() }}">
        <span class="icon">{!! app_icon('back') !!}</span> Retour
    </a>
@endsection

@section('content')
    <div class="grid-2">
        <div class="panel">
            <h2>Informations générales</h2>
            <dl class="definition-list">
                <div><dt>Code</dt><dd>{{ $gare->code }}</dd></div>
                <div><dt>Ville</dt><dd>{{ $gare->city }}</dd></div>
                <div><dt>Zone</dt><dd>{{ $gare->zone ?: '—' }}</dd></div>
                <div><dt>Adresse</dt><dd>{{ $gare->address ?: '—' }}</dd></div>
                <div><dt>Statut</dt><dd><span class="badge {{ $gare->is_active ? 'badge-success' : 'badge-danger' }}">{{ $gare->is_active ? 'Active' : 'Inactive' }}</span></dd></div>
            </dl>
        </div>

        <div class="panel">
            <h2>Volumes</h2>
            <div class="stats-grid">
                <x-stat-card title="Recettes" :value="$gare->recettes_count" icon="wallet" />
                <x-stat-card title="Dépenses" :value="$gare->depenses_count" icon="receipt" />
                <x-stat-card title="Versements" :value="$gare->versements_bancaires_count" icon="bank" />
            </div>
        </div>
    </div>
@endsection
