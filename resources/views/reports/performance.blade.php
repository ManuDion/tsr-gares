@extends('layouts.app')

@section('title', 'Top 5 & Rapports')
@section('heading', ($module?->value ?? 'gares') === 'courrier' ? 'Top 5 et rapports — Service courrier' : 'Top 5 et rapports de supervision')
@section('subheading', 'Accessible uniquement à l’administrateur et au responsable')

@section('content')
    <div class="panel">
        <form method="GET" class="filters-grid">
            <input type="hidden" name="module" value="{{ $module->value }}">
            <div>
                <label>Date début</label>
                <input type="date" name="start_date" value="{{ $startDate }}">
            </div>
            <div>
                <label>Date fin</label>
                <input type="date" name="end_date" value="{{ $endDate }}">
            </div>
            <div class="align-end">
                <button class="btn btn-primary" type="submit"><span class="icon">{!! app_icon('filter') !!}</span> Filtrer</button>
            </div>
        </form>
    </div>

    <div class="grid-3">
        <div class="panel">
            <h2>Top 5 en saisie</h2>
            <div class="mini-bars">
                @forelse ($topSaisie as $gare)
                    @php $width = $topSaisie->max('saisie_total') > 0 ? ($gare->saisie_total / $topSaisie->max('saisie_total')) * 100 : 0; @endphp
                    <div class="mini-bar-row">
                        <div class="mini-bar-header">
                            <strong>{{ $gare->name }}</strong>
                            <span>{{ $gare->saisie_total }} saisies</span>
                        </div>
                        <div class="mini-bar-track"><div class="mini-bar-fill" style="width: {{ $width }}%"></div></div>
                    </div>
                @empty
                    <p class="text-muted">Aucune donnée sur cette période.</p>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <h2>Top 5 recettes</h2>
            <div class="mini-bars">
                @forelse ($topRecettes as $row)
                    @php $width = $topRecettes->max('total_amount') > 0 ? ($row->total_amount / $topRecettes->max('total_amount')) * 100 : 0; @endphp
                    <div class="mini-bar-row">
                        <div class="mini-bar-header">
                            <strong>{{ $row->gare?->name ?? 'Gare' }}</strong>
                            <span>{{ number_format($row->total_amount, 0, ',', ' ') }} FCFA</span>
                        </div>
                        <div class="mini-bar-track"><div class="mini-bar-fill" style="width: {{ $width }}%"></div></div>
                    </div>
                @empty
                    <p class="text-muted">Aucune recette sur cette période.</p>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <h2>Top 5 dépenses</h2>
            <div class="mini-bars">
                @forelse ($topDepenses as $row)
                    @php $width = $topDepenses->max('total_amount') > 0 ? ($row->total_amount / $topDepenses->max('total_amount')) * 100 : 0; @endphp
                    <div class="mini-bar-row">
                        <div class="mini-bar-header">
                            <strong>{{ $row->gare?->name ?? 'Gare' }}</strong>
                            <span>{{ number_format($row->total_amount, 0, ',', ' ') }} FCFA</span>
                        </div>
                        <div class="mini-bar-track"><div class="mini-bar-fill" style="width: {{ $width }}%"></div></div>
                    </div>
                @empty
                    <p class="text-muted">Aucune dépense sur cette période.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
