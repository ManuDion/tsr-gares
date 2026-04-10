@extends('layouts.app')

@section('title', 'Nouvelle gare')
@section('heading', 'Créer une gare')

@section('content')
    <div class="panel panel-narrow">
        <form method="POST" action="{{ route('gares.store') }}" class="stack-md">
            @csrf
            @include('gares._form')
            <div class="form-actions">
                <a class="btn btn-outline" href="{{ route('gares.index') }}">Annuler</a>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
@endsection
