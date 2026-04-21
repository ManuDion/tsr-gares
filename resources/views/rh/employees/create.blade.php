@extends('layouts.app')

@section('title', 'Nouveau dossier RH')
@section('heading', 'Nouveau dossier RH')
@section('subheading', 'Création du dossier numérique de l’agent et préparation de son espace personnel.')

@section('content')
    <div class="panel">
        <form method="POST" action="{{ route('rh.employees.store', ['module' => 'rh']) }}" class="stack-md">
            @csrf
            @include('rh.employees._form')
            <div class="form-actions">
                <a class="btn btn-outline" href="{{ route('rh.employees.index', ['module' => 'rh']) }}">Annuler</a>
                <button class="btn btn-primary" type="submit">Créer le dossier</button>
            </div>
        </form>
    </div>
@endsection
