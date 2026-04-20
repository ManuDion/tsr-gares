@extends('layouts.app')

@section('title', 'Nouveau document administratif')
@section('heading', 'Ajouter un document administratif')
@section('subheading', 'PDF + type de document + date d’expiration + rappel automatique')

@section('actions')
    <a class="btn btn-outline" href="{{ route('administrative-documents.index') }}">
        <span class="icon">{!! app_icon('back') !!}</span> Retour à la liste
    </a>
@endsection

@section('content')
    <div class="panel panel-narrow">
        <form method="POST" action="{{ route('administrative-documents.store') }}" enctype="multipart/form-data" class="stack-md">
            @csrf
            @include('administrative-documents._form')
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">
                    <span class="icon">{!! app_icon('plus') !!}</span> Enregistrer
                </button>
            </div>
        </form>
    </div>
@endsection
