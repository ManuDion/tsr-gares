@extends('layouts.app')

@section('title', 'Nouvelle recette')
@section('heading', ($module?->value ?? 'gares') === 'courrier' ? 'Saisir une recette courrier' : 'Saisir une recette')

@section('content')
    <div class="panel panel-narrow">
        <form method="POST" action="{{ route('recettes.store', ['module' => $module->value]) }}" enctype="multipart/form-data" class="stack-md">
            @csrf
            <input type="hidden" name="module" value="{{ $module->value }}">
            @include('recettes._form')
            <div class="form-actions">
                <a class="btn btn-outline" href="{{ route('recettes.index', ['module' => $module->value]) }}">Annuler</a>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
@endsection
