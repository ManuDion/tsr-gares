@extends('layouts.app')

@section('title', 'Modifier dossier RH')
@section('heading', 'Modifier dossier RH')
@section('subheading', 'Mise à jour des informations administratives de l’agent.')

@section('content')
    <div class="panel">
        <form method="POST" action="{{ route('rh.employees.update', ['employee' => $employee, 'module' => 'rh']) }}" class="stack-md">
            @csrf
            @method('PUT')
            @include('rh.employees._form', ['employee' => $employee])
            <div class="form-actions">
                <a class="btn btn-outline" href="{{ route('rh.employees.show', ['employee' => $employee, 'module' => 'rh']) }}">Retour</a>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
@endsection
