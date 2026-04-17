@extends('layouts.app')

@section('title', 'Nouveau versement')
@section('heading', 'Versement bancaire avec lecture du bordereau')
@section('subheading', 'Le bordereau est obligatoire. Téléversez-le, vérifiez les champs préremplis puis validez.')

@section('content')
    <div class="stack-lg">
        <div class="panel">
            <h2>1. Téléchargement du bordereau</h2>
            <p class="muted">PDF, photo mobile ou scan. La caméra mobile est acceptée. Le bordereau reste obligatoire, même en saisie manuelle.</p>

            <form method="POST" action="{{ route('versements.analyze') }}" enctype="multipart/form-data" class="stack-md">
                @csrf
                <div>
                    <label>Bordereau de versement (max {{ $maxSizeKb }} Ko)</label>
                    <input type="file" name="bordereau" accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf" capture="environment" required>
                    <small>Le document est lu automatiquement. Si l'OCR échoue, vous pourrez tout de même valider manuellement avec le bordereau déjà attaché.</small>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">
                        <span class="icon">{!! app_icon('sparkles') !!}</span>
                        Lire et préremplir
                    </button>
                    <a class="btn btn-outline" href="{{ route('versements.create', ['manual' => 1]) }}">Saisie manuelle</a>
                </div>
            </form>
        </div>

        <div class="panel">
            <h2>2. Validation utilisateur</h2>

            @if($draft || $manualMode)
                <form method="POST" action="{{ route('versements.store') }}" enctype="multipart/form-data" class="stack-md">
                    @csrf
                    @include('versements._form', ['versement' => null])
                    <div class="form-actions">
                        <a class="btn btn-outline" href="{{ route('versements.index') }}">Annuler</a>
                        <button class="btn btn-primary" type="submit">
                            <span class="icon">{!! app_icon('plus') !!}</span>
                            Valider et enregistrer
                        </button>
                    </div>
                </form>
            @else
                <div class="empty-state">
                    <strong>Analyse en attente</strong>
                    <p>Téléchargez le bordereau à l’étape 1 pour obtenir les champs préremplis, puis validez après vérification.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
