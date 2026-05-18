@extends('layouts.app')

@section('title', 'Receptions caissier')
@section('heading', 'Validation des sommes recues')
@section('subheading', 'Le caissier valide les ecritures des gares avant alimentation de son compte')

@section('content')
    @php
        $collectsInter = $collectsInter ?? true;
        $collectsNational = $collectsNational ?? true;
    @endphp

    <div class="panel">
        <form method="GET" class="filters-grid">
            <input type="hidden" name="module" value="{{ $module->value }}">
            <div>
                <label>Date operation</label>
                <input type="date" name="operation_date" value="{{ $operationDate }}">
            </div>
            <div class="align-end">
                <button class="btn btn-outline" type="submit">Actualiser</button>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Gare</th>
                    <th>Recette Inter</th>
                    <th>Recette Nationale</th>
                    <th>Dépenses</th>
                    <th>Total attendu</th>
                    <th>Numéro de téléphone</th>
                    <th>Approbation caissier</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php
                        $gare = $row['gare'];
                        $expected = $row['expected'];
                        $confirmation = $row['confirmation'];
                        $isLocked = (bool) ($row['is_locked'] ?? false);
                        $validateInter = $collectsInter ? (int) round((float) ($confirmation->received_inter_total ?? $expected['expected_inter']), 0) : 0;
                        $validateNational = $collectsNational ? (int) round((float) ($confirmation->received_national_total ?? $expected['expected_national']), 0) : 0;
                        $validateTotal = $validateInter + $validateNational;
                    @endphp
                    <tr>
                        <td>{{ $gare->name }}</td>
                        <td>{{ number_format($expected['recette_inter'], 0, '', ' ') }}</td>
                        <td>{{ number_format($expected['recette_national'], 0, '', ' ') }}</td>
                        <td><strong>{{ number_format($expected['depense_total'], 0, '', ' ') }}</strong></td>
                        <td><strong>{{ number_format($expected['expected_total'], 0, '', ' ') }}</strong></td>
                        <td>{{ $row['phone'] ?: '-' }}</td>
                        <td>
                            <form method="POST" action="{{ route('cashier-receipts.store', ['module' => $module->value]) }}" class="stack-sm">
                                @csrf
                                <input type="hidden" name="gare_id" value="{{ $gare->id }}">
                                <input type="hidden" name="operation_date" value="{{ $operationDate }}">
                                <input type="hidden" name="received_inter_total" value="{{ $validateInter }}">
                                <input type="hidden" name="received_national_total" value="{{ $validateNational }}">
                                <input type="hidden" name="received_total" value="{{ $validateTotal }}">
                                <button class="btn btn-sm btn-primary" type="submit" name="mode" value="validate">Valider</button>
                            </form>

                            @if($isLocked)
                                <form method="POST" action="{{ route('cashier-receipts.store', ['module' => $module->value]) }}" class="stack-sm mt-xxs">
                                    @csrf
                                    <input type="hidden" name="gare_id" value="{{ $gare->id }}">
                                    <input type="hidden" name="operation_date" value="{{ $operationDate }}">
                                    <input type="hidden" name="received_inter_total" value="{{ $validateInter }}">
                                    <input type="hidden" name="received_national_total" value="{{ $validateNational }}">
                                    <input type="hidden" name="received_total" value="{{ $validateTotal }}">
                                    <div class="unlock-controls">
                                        <input type="number" name="unlock_duration" min="1" step="1" value="{{ old('unlock_duration', 24) }}" class="unlock-duration" required>
                                        <select name="unlock_unit" class="unlock-unit" required>
                                            <option value="minutes" @selected(old('unlock_unit') === 'minutes')>Minutes</option>
                                            <option value="hours" @selected(old('unlock_unit', 'hours') === 'hours')>Heures</option>
                                            <option value="days" @selected(old('unlock_unit') === 'days')>Jours</option>
                                        </select>
                                        <button class="btn btn-sm btn-outline" type="submit" name="mode" value="unlock">Deverrouiller</button>
                                    </div>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Aucune ligne a afficher pour cette date.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
