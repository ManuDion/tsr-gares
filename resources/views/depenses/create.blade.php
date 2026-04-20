@extends('layouts.app')

@section('title', 'Nouvelle dépense')
@section('heading', 'Saisir une dépense')
@section('subheading', 'Le justificatif est accepté en PDF, JPG ou PNG puis stocké de manière privée')

@section('content')
    <div class="panel panel-narrow">
        <form method="POST" action="{{ route('depenses.store') }}" enctype="multipart/form-data" class="stack-md">
            @csrf
            @include('depenses._form', ['depense' => null])
            <div class="form-actions">
                <a class="btn btn-outline" href="{{ route('depenses.index') }}">Annuler</a>
                <button class="btn btn-primary" type="submit"><span class="icon">{!! app_icon('plus') !!}</span> Enregistrer</button>
            </div>
        </form>
    </div>
@endsection
