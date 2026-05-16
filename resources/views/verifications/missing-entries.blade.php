@extends('layouts.app')

@section('title', 'Écritures manquantes')
@section('heading', 'Fiche des écritures manquantes')
@section('subheading', 'Liste journalière des gares avec écritures non finalisées')

@section('actions')
    <a class="btn btn-outline" href="{{ route('verifications.index', ['module' => $module->value, 'operation_date' => $operationDate]) }}">
        Retour vérification
    </a>
@endsection

@section('content')
    <div class="panel">
        <form method="GET" class="filters-grid">
            <input type="hidden" name="module" value="{{ $module->value }}">
            <div>
                <label>Date opération</label>
                <input type="date" name="operation_date" value="{{ $operationDate }}">
            </div>
            <div class="align-end gap-sm">
                <button class="btn btn-outline" type="submit">
                    <span class="icon">{!! app_icon('filter') !!}</span> Filtrer
                </button>
                <a class="btn btn-primary" href="{{ route('verifications.missing-entries.pdf', ['module' => $module->value, 'operation_date' => $operationDate]) }}" target="_blank">
                    Exporter PDF
                </a>
            </div>
        </form>
    </div>

    <div class="table-wrapper missing-entries-table">
        <table>
            <thead>
                <tr>
                    <th>Gare</th>
                    <th>Recette</th>
                    <th>Dépense</th>
                    <th>Versement Coris</th>
                    <th>Versement Ecobank</th>
                    <th>Numéro de téléphone</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row['gare'] }}</td>
                        <td>
                            <span class="badge badge-nowrap {{ $row['recette_missing'] ? 'badge-danger' : 'badge-success' }}">
                                {{ $row['recette_missing'] ? 'Manquante' : 'OK' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-nowrap {{ $row['depense_missing'] ? 'badge-danger' : 'badge-success' }}">
                                {{ $row['depense_missing'] ? 'Manquante' : 'OK' }}
                            </span>
                        </td>
                        <td class="{{ $row['is_cashier_managed'] ? 'cashier-missing-cell' : '' }}">
                            @if($row['versement_coris_missing'] === null)
                                <span class="text-muted">N/A</span>
                            @elseif($row['is_cashier_managed'])
                                <span class="badge badge-danger badge-centered">Validation caissier manquante</span>
                            @else
                                <span class="badge badge-nowrap {{ $row['versement_coris_missing'] ? 'badge-danger' : 'badge-success' }}">
                                    {{ $row['versement_coris_missing'] ? 'Manquant' : 'OK' }}
                                </span>
                            @endif
                        </td>
                        <td class="{{ $row['is_cashier_managed'] ? 'cashier-missing-cell' : '' }}">
                            @if($row['versement_ecobank_missing'] === null)
                                <span class="text-muted">N/A</span>
                            @elseif($row['is_cashier_managed'])
                                <span class="badge badge-danger badge-centered">Validation caissier manquante</span>
                            @else
                                <span class="badge badge-nowrap {{ $row['versement_ecobank_missing'] ? 'badge-danger' : 'badge-success' }}">
                                    {{ $row['versement_ecobank_missing'] ? 'Manquant' : 'OK' }}
                                </span>
                            @endif
                        </td>
                        <td>{{ $row['phone'] ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">Aucune écriture manquante pour cette date.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

