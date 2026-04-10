@extends('layouts.app')

@section('title', 'Modifier utilisateur')
@section('heading', 'Modifier un utilisateur')

@section('content')
    <div class="panel panel-narrow">
        <form method="POST" action="{{ route('users.update', $user) }}" class="stack-md">
            @csrf
            @method('PUT')
            @include('users._form', ['user' => $user])
            <div class="form-actions">
                <a class="btn btn-outline" href="{{ route('users.index') }}">Retour</a>
                <button class="btn btn-primary" type="submit">Mettre à jour</button>
            </div>
        </form>
    </div>
@endsection
