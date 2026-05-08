@extends('layouts.app')

@section('title', 'Receptions caissier')
@section('heading', 'Validation des sommes recues')
@section('subheading', 'Le caissier valide les ecritures des gares avant alimentation de son compte')

@section('content')
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
                    <th>Attendu Inter</th>
                    <th>Attendu National</th>
                    <th>Attendu Total</th>
                    <th>Saisie caissier</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php
                        $gare = $row['gare'];
                        $expected = $row['expected'];
                        $confirmation = $row['confirmation'];
                    @endphp
                    <tr>
                        <td>{{ $gare->name }}</td>
                        <td>{{ number_format($expected['expected_inter'], 0, ',', ' ') }}</td>
                        <td>{{ number_format($expected['expected_national'], 0, ',', ' ') }}</td>
                        <td><strong>{{ number_format($expected['expected_total'], 0, ',', ' ') }}</strong></td>
                        <td>
                            <form method="POST" action="{{ route('cashier-receipts.store', ['module' => $module->value]) }}" class="stack-sm">
                                @csrf
                                <input type="hidden" name="gare_id" value="{{ $gare->id }}">
                                <input type="hidden" name="operation_date" value="{{ $operationDate }}">
                                <input type="hidden" name="received_inter_total" value="{{ $expected['expected_inter'] }}">
                                <input type="hidden" name="received_national_total" value="{{ $expected['expected_national'] }}">
                                <input type="hidden" name="received_total" value="{{ $expected['expected_total'] }}">
                                <input type="hidden" name="is_verified" value="1">
                                <div>
                                    @if($confirmation?->is_verified)
                                        <span class="badge badge-success">
                                            Valide le {{ optional($confirmation->verified_at)->format('d/m/Y H:i') }}
                                        </span>
                                    @else
                                        <span class="badge badge-warning">En attente de validation</span>
                                    @endif
                                </div>
                                <div>
                                    <input type="text" name="note" value="{{ old('note', $confirmation->note ?? '') }}" placeholder="Note (optionnel)">
                                </div>
                                <button class="btn btn-sm btn-primary" type="submit">Valider</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Aucune ligne en attente de validation.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
