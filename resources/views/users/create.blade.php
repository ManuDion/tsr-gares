@extends('layouts.app')

@section('title', 'Nouvel utilisateur')
@section('heading', 'Créer un utilisateur')

@section('content')
    <div class="panel panel-narrow">
        <form method="POST" action="{{ route('users.store') }}" class="stack-md">
            @csrf
            @include('users._form')
            <div class="form-actions">
                <a class="btn btn-outline" href="{{ route('users.index') }}">Annuler</a>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
@endsection
