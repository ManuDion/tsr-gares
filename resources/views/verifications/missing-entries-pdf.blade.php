<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche écritures manquantes</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; margin: 18px; font-size: 12px; }
        h1 { margin: 0 0 4px; font-size: 20px; }
        p { margin: 0 0 12px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 7px; text-align: left; }
        th { background: #f3f4f6; font-weight: 700; }
        th.col-depense { white-space: nowrap; }
        .ok { color: #166534; font-weight: 700; }
        .ko { color: #b91c1c; font-weight: 700; }
        .na { color: #6b7280; }
        .nowrap { white-space: nowrap; }
        .cashier-missing { text-align: center; white-space: normal; line-height: 1.25; }
    </style>
</head>
<body>
    <h1>Fiche des écritures manquantes</h1>
    <p>Module: {{ $module->shortLabel() }} | Période: {{ $periodLabel }}</p>

    <table>
        <thead>
            <tr>
                <th>Gare</th>
                <th>Date</th>
                <th>Recette</th>
                <th class="col-depense">Dépense</th>
                <th>Versement Coris</th>
                <th>Versement Ecobank</th>
                <th>Numéro de téléphone</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['gare'] }}</td>
                    <td class="nowrap">{{ \Carbon\Carbon::parse($row['operation_date'])->format('d/m/Y') }}</td>
                    <td class="nowrap {{ $row['recette_missing'] ? 'ko' : 'ok' }}">{{ $row['recette_missing'] ? 'Manquant' : 'OK' }}</td>
                    <td class="nowrap {{ $row['depense_missing'] ? 'ko' : 'ok' }}">{{ $row['depense_missing'] ? 'Manquant' : 'OK' }}</td>
                    <td class="{{ ! ($row['versement_coris_applicable'] ?? true) ? '' : ($row['versement_coris_missing'] ? 'ko' : 'ok') }} {{ $row['is_cashier_managed'] ? 'cashier-missing' : 'nowrap' }}">
                        {{ $row['is_cashier_managed'] ? 'Confie au caissier : '.($row['cashier_name'] ?? '-') : (! ($row['versement_coris_applicable'] ?? true) ? '' : ($row['versement_coris_missing'] ? 'Manquant' : 'OK')) }}
                    </td>
                    <td class="{{ ! ($row['versement_ecobank_applicable'] ?? true) ? '' : ($row['versement_ecobank_missing'] ? 'ko' : 'ok') }} {{ $row['is_cashier_managed'] ? 'cashier-missing' : 'nowrap' }}">
                        {{ $row['is_cashier_managed'] ? 'Confie au caissier : '.($row['cashier_name'] ?? '-') : (! ($row['versement_ecobank_applicable'] ?? true) ? '' : ($row['versement_ecobank_missing'] ? 'Manquant' : 'OK')) }}
                    </td>
                    <td class="nowrap">{{ $row['phone'] ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Aucune écriture manquante sur cette période.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

