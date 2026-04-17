@extends('layouts.app')

@section('title', 'Nouvelle recette')
@section('heading', 'Saisir une recette')

@section('content')
    <div class="panel panel-narrow">
        <form method="POST" action="{{ route('recettes.store') }}" enctype="multipart/form-data" class="stack-md">
            @csrf
            @include('recettes._form')
            <div class="form-actions">
                <a class="btn btn-outline" href="{{ route('recettes.index') }}">Annuler</a>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
@endsection
