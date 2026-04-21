@extends('layouts.app')

@section('title', 'Nouveau versement')
@section('heading', ($module?->value ?? 'gares') === 'courrier' ? 'Nouveau versement courrier' : 'Nouveau versement bancaire')
@section('subheading', 'Téléversez le bordereau ou prenez une photo depuis le téléphone, puis renseignez les informations manuellement.')

@section('content')
    <div class="panel panel-narrow">
        <form method="POST" action="{{ route('versements.store', ['module' => $module->value]) }}" enctype="multipart/form-data" class="stack-md">
            @csrf
            <input type="hidden" name="module" value="{{ $module->value }}">
            @include('versements._form', ['versement' => null, 'maxSizeKb' => $maxSizeKb])

            <div class="form-actions">
                <a class="btn btn-outline" href="{{ route('versements.index', ['module' => $module->value]) }}">Annuler</a>
                <button class="btn btn-primary" type="submit">
                    <span class="icon">{!! app_icon('plus') !!}</span>
                    Enregistrer le versement
                </button>
            </div>
        </form>
    </div>
@endsection
