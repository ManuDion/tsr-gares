@extends('layouts.app')

@section('title', 'Dossiers RH')
@section('heading', 'Dossiers du personnel')
@section('subheading', 'Constitution et suivi du dossier numérique unique par agent.')

@section('actions')
    @if(! auth()->user()->isPersonnelTsr())
        <a class="btn btn-primary" href="{{ route('rh.employees.create', ['module' => 'rh']) }}">
            <span class="icon">{!! app_icon('plus') !!}</span> Nouveau dossier
        </a>
    @endif
@endsection

@section('content')
    <div class="panel">
        <form method="GET" class="filters-grid">
            <input type="hidden" name="module" value="rh">
            <div>
                <label>Recherche</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Nom, code, téléphone ou email">
            </div>
            <div class="align-end">
                <button class="btn btn-outline" type="submit"><span class="icon">{!! app_icon('filter') !!}</span> Filtrer</button>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Code agent</th>
                    <th>Nom complet</th>
                    <th>Service</th>
                    <th>Fonction</th>
                    <th>Gare</th>
                    <th>Compte lié</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                    <tr>
                        <td>{{ $employee->employee_code }}</td>
                        <td>{{ $employee->full_name }}</td>
                        <td>{{ $employee->department?->name ?? '—' }}</td>
                        <td>{{ $employee->job_title ?? '—' }}</td>
                        <td>{{ $employee->gare?->name ?? '—' }}</td>
                        <td>{{ $employee->user?->email ?? 'Non lié' }}</td>
                        <td class="actions-cell">
                            <a class="btn btn-sm btn-outline" href="{{ route('rh.employees.show', ['employee' => $employee, 'module' => 'rh']) }}">
                                <span class="icon">{!! app_icon('eye') !!}</span>
                            </a>
                            @if(! auth()->user()->isPersonnelTsr())
                                <a class="btn btn-sm btn-outline" href="{{ route('rh.employees.edit', ['employee' => $employee, 'module' => 'rh']) }}">
                                    <span class="icon">{!! app_icon('edit') !!}</span>
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Aucun dossier RH enregistré.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $employees->links() }}
@endsection
