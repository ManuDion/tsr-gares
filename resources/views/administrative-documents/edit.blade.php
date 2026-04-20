@extends('layouts.app')

@section('title', 'Mettre à jour un document administratif')
@section('heading', 'Mise à jour du document administratif')
@section('subheading', 'Le changement de PDF ou de date d’expiration renouvelle le suivi')

@section('actions')
    <a class="btn btn-outline" href="{{ route('administrative-documents.index') }}">
        <span class="icon">{!! app_icon('back') !!}</span> Retour à la liste
    </a>
@endsection

@section('content')
    <div class="panel panel-narrow">
        <form method="POST" action="{{ route('administrative-documents.update', $document) }}" enctype="multipart/form-data" class="stack-md">
            @csrf
            @method('PUT')
            @include('administrative-documents._form', ['document' => $document])
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">
                    <span class="icon">{!! app_icon('edit') !!}</span> Mettre à jour
                </button>
            </div>
        </form>
    </div>
@endsection
