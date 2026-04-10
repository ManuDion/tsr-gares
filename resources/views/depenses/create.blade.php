@extends('layouts.app')

@section('title', 'Nouvelle dépense')
@section('heading', 'Saisir une dépense')
@section('subheading', 'Le justificatif est accepté en PDF, JPG ou PNG puis stocké de manière privée')

@section('content')
    <div class="panel panel-narrow">
        <form method="POST" action="{{ route('depenses.store') }}" enctype="multipart/form-data" class="stack-md">
            @csrf
            <div class="form-grid">
                @unless(auth()->user()->isChefDeGare())
                    <x-gare-picker :gares="$gares" datalistId="depense-gares" :selectedGareLabel="collect($gares)->firstWhere('id', (int) old('gare_id', 0))?->name . ' — ' . collect($gares)->firstWhere('id', (int) old('gare_id', 0))?->city" :selectedGareId="old('gare_id')" />
                @else
                    <div>
                        <label>Gare affectée</label>
                        <input type="text" value="{{ auth()->user()->primaryGare?->name }}" disabled>
                    </div>
                @endunless

                <div>
                    <label>Date opération</label>
                    <input type="date" name="operation_date" value="{{ old('operation_date', now()->toDateString()) }}" required>
                </div>
                <div>
                    <label>Montant</label>
                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" required>
                </div>
                <div>
                    <label>Motif</label>
                    <input type="text" name="motif" value="{{ old('motif') }}" required>
                </div>
                <div>
                    <label>Référence</label>
                    <input type="text" name="reference" value="{{ old('reference') }}">
                </div>
                <div class="col-span-2">
                    <label>Description</label>
                    <textarea name="description" rows="4">{{ old('description') }}</textarea>
                </div>
                <div class="col-span-2">
                    <label>Justificatif (max {{ $maxSizeKb }} Ko)</label>
                    <input type="file" name="justificatif" accept=".pdf,.jpg,.jpeg,.png">
                    <small>Le rendu PDF unifié et l’OCR sont prévus par l’architecture et prêts à être renforcés.</small>
                </div>
            </div>
            <div class="form-actions">
                <a class="btn btn-outline" href="{{ route('depenses.index') }}">Annuler</a>
                <button class="btn btn-primary" type="submit"><span class="icon">{!! app_icon('plus') !!}</span> Enregistrer</button>
            </div>
        </form>
    </div>
@endsection
