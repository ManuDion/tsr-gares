@extends('layouts.app')

@section('title', 'Pieces justificatives')
@section('heading', 'Pieces justificatives')
@section('subheading', 'Telechargement par lot des justificatifs des gares actives')

@section('content')
    <div class="panel">
        <div class="justificatifs-tabs" role="tablist" aria-label="Types de justificatifs">
            @foreach($tabs as $tabKey => $tab)
                <a
                    href="{{ route('justificatifs-batch.index', ['module' => request('module', $module->value), 'tab' => $tabKey]) }}"
                    class="module-chip {{ $activeTab === $tabKey ? 'active' : '' }}"
                    role="tab"
                    aria-selected="{{ $activeTab === $tabKey ? 'true' : 'false' }}">
                    {{ $tab['title'] }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="table-wrapper table-compact justificatifs-batch-table">
        <table>
            <thead>
                <tr>
                    <th>Nom gare</th>
                    <th>Chef de gare</th>
                    <th>Numéro de téléphone</th>
                    <th>Télécharger</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php($gare = $row['gare'])
                    <tr>
                        <td data-label="Nom gare" title="{{ $gare->name }}">{{ $gare->name }}</td>
                        <td data-label="Chef de gare" title="{{ $row['chef_name'] }}">{{ $row['chef_name'] }}</td>
                        <td data-label="Numéro de téléphone">{{ $row['chef_phone'] }}</td>
                        <td data-label="Télécharger">
                            <a
                                class="btn btn-sm btn-outline"
                                href="{{ route('justificatifs-batch.period', ['module' => request('module', $module->value), 'type' => $activeTab, 'gare' => $gare]) }}">
                                <span class="icon">{!! app_icon('download') !!}</span>
                                Télécharger
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">Aucune gare active disponible.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
