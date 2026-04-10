@extends('layouts.app')

@section('title', 'Modifier une gare')
@section('heading', 'Modifier la gare')

@section('content')
    <div class="panel panel-narrow">
        <form method="POST" action="{{ route('gares.update', $gare) }}" class="stack-md">
            @csrf
            @method('PUT')
            @include('gares._form', ['gare' => $gare])
            <div class="form-actions">
                <a class="btn btn-outline" href="{{ route('gares.index') }}">Retour</a>
                <button class="btn btn-primary" type="submit">Mettre à jour</button>
            </div>
        </form>
    </div>
@endsection
