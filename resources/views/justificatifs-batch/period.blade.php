@extends('layouts.app')

@section('title', 'Filtre justificatifs')
@section('heading', $typeConfig['title'])
@section('subheading', 'Selectionnez la periode de telechargement pour la gare '.$gare->name)

@section('actions')
    <a class="btn btn-outline" href="{{ route('justificatifs-batch.index', ['module' => request('module', $module->value), 'tab' => $type]) }}">
        <span class="icon">{!! app_icon('back') !!}</span> Retour
    </a>
@endsection

@section('content')
    <div class="panel panel-narrow">
        <form method="POST" action="{{ route('justificatifs-batch.download', ['module' => request('module', $module->value), 'type' => $type, 'gare' => $gare]) }}" class="filters-grid justificatifs-period-form">
            @csrf
            <div>
                <label>Période</label>
                <select name="period" data-period-select>
                    <option value="custom">Personnalisee</option>
                    <option value="today">Aujourd'hui</option>
                    <option value="week">7 derniers jours</option>
                    <option value="month">Mois en cours</option>
                </select>
            </div>
            <div>
                <label>Date de debut</label>
                <input type="date" name="start_date" value="{{ old('start_date', $defaultStartDate) }}" required data-period-start>
                @error('start_date')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>
            <div>
                <label>Date de fin</label>
                <input type="date" name="end_date" value="{{ old('end_date', $defaultEndDate) }}" required data-period-end>
                @error('end_date')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>
            <div class="align-end">
                <button class="btn btn-primary" type="submit" title="Télécharger" aria-label="Télécharger">
                    <span class="icon">{!! app_icon('download') !!}</span>
                    <span class="sr-only">Télécharger</span>
                </button>
            </div>
        </form>

        @error('period')
            <p class="text-danger">{{ $message }}</p>
        @enderror

        <p class="text-muted">
            Le fichier ZIP telecharge prendra le format <strong>NomGare_DateTelechargement</strong>.
        </p>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const periodSelect = document.querySelector('[data-period-select]');
            const startInput = document.querySelector('[data-period-start]');
            const endInput = document.querySelector('[data-period-end]');
            if (!periodSelect || !startInput || !endInput) return;

            function toYmd(date) {
                const y = date.getFullYear();
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const d = String(date.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            }

            function setPeriod(value) {
                const now = new Date();
                const end = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                const start = new Date(end.getTime());

                if (value === 'today') {
                    // same day
                } else if (value === 'week') {
                    start.setDate(start.getDate() - 6);
                } else if (value === 'month') {
                    start.setDate(1);
                } else {
                    return;
                }

                startInput.value = toYmd(start);
                endInput.value = toYmd(end);
            }

            periodSelect.addEventListener('change', function() {
                setPeriod(periodSelect.value);
            });
        })();
    </script>
@endpush
