@extends('layouts.app')

@section('title', 'Dossier RH')
@section('heading', $employee->full_name)
@section('subheading', 'Dossier numérique unique de l’agent, affectations et pièces RH.')

@section('actions')
    @if(! auth()->user()->isPersonnelTsr())
        <a class="btn btn-outline" href="{{ route('rh.employees.edit', ['employee' => $employee, 'module' => 'rh']) }}">
            <span class="icon">{!! app_icon('edit') !!}</span> Modifier
        </a>
    @endif
@endsection

@section('content')
    <div class="grid-2">
        <div class="panel">
            <h2>Fiche agent</h2>
            <dl class="details-grid">
                <div><dt>Code</dt><dd>{{ $employee->employee_code }}</dd></div>
                <div><dt>Nom complet</dt><dd>{{ $employee->full_name }}</dd></div>
                <div><dt>Téléphone</dt><dd>{{ $employee->phone ?? '—' }}</dd></div>
                <div><dt>Email</dt><dd>{{ $employee->email ?? '—' }}</dd></div>
                <div><dt>Fonction</dt><dd>{{ $employee->job_title ?? '—' }}</dd></div>
                <div><dt>Date d’embauche</dt><dd>{{ $employee->hire_date?->format('d/m/Y') ?? '—' }}</dd></div>
                <div><dt>Service</dt><dd>{{ $employee->department?->name ?? '—' }}</dd></div>
                <div><dt>Gare</dt><dd>{{ $employee->gare?->name ?? '—' }}</dd></div>
                <div><dt>Compte utilisateur</dt><dd>{{ $employee->user?->email ?? 'Non lié' }}</dd></div>
                <div><dt>Mobile</dt><dd>{{ $employee->mobile_app_enabled ? 'Oui' : 'Non' }}</dd></div>
            </dl>
        </div>

        <div class="panel">
            <h2>Affectations</h2>
            <div class="table-wrapper table-plain">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Service</th>
                            <th>Gare</th>
                            <th>Fonction</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employee->assignments as $assignment)
                            <tr>
                                <td>{{ $assignment->assigned_at?->format('d/m/Y') }}</td>
                                <td>{{ $assignment->department?->name ?? '—' }}</td>
                                <td>{{ $assignment->gare?->name ?? '—' }}</td>
                                <td>{{ $assignment->job_title ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">Aucune affectation enregistrée.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid-2">
        @if(! auth()->user()->isPersonnelTsr())
            <div class="panel">
                <h2>Ajouter une pièce RH</h2>
                <form method="POST" action="{{ route('rh.employees.documents.store', ['employee' => $employee, 'module' => 'rh']) }}" enctype="multipart/form-data" class="stack-md">
                    @csrf
                    <div>
                        <label>Type de document</label>
                        <input type="text" name="document_type" required placeholder="Contrat, bulletin de salaire, décision d'affectation...">
                    </div>
                    <div>
                        <label>Libellé</label>
                        <input type="text" name="label" placeholder="Nom personnalisé du document">
                    </div>
                    <div>
                        <label>Date d'expiration</label>
                        <input type="date" name="expires_at">
                    </div>
                    <div>
                        <label>Fichier</label>
                        <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                    </div>
                    <div>
                        <label>Notes</label>
                        <textarea name="notes" rows="3"></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Ajouter</button>
                </form>
            </div>
        @endif

        <div class="panel">
            <h2>Pièces du dossier</h2>
            <div class="table-wrapper table-plain">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Nom</th>
                            <th>Expiration</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employee->documents as $document)
                            <tr>
                                <td>{{ $document->document_type }}</td>
                                <td>{{ $document->file_name }}</td>
                                <td>{{ $document->expires_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="actions-cell">
                                    @if(auth()->user()->isAdmin() || auth()->user()->isResponsableRh())
                                        <form method="POST" action="{{ route('rh.employees.documents.destroy', ['employee' => $employee, 'document' => $document, 'module' => 'rh']) }}" onsubmit="return confirm('Supprimer ce document ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger" type="submit">
                                                <span class="icon">{!! app_icon('trash') !!}</span>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4">Aucune pièce RH dans ce dossier.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
