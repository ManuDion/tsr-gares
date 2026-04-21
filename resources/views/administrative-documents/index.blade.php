@extends('layouts.app')

@section('title', 'Documents administratifs')
@section('heading', 'Documents administratifs')
@section('subheading', 'Suivi des échéances réglementaires et rappels automatiques')

@section('actions')
    @can('create', App\Models\AdministrativeDocument::class)
        <a class="btn btn-primary" href="{{ route('administrative-documents.create') }}">
            <span class="icon">{!! app_icon('plus') !!}</span> Nouveau document
        </a>
    @endcan
@endsection

@section('content')
    <div class="panel">
        <form method="GET" class="filters-grid">
            <div>
                <label>Type de document</label>
                <input type="text" name="document_type" value="{{ request('document_type') }}" placeholder="Permis, vignette...">
            </div>
            <div>
                <label>Statut</label>
                <select name="status">
                    <option value="">Tous</option>
                    <option value="active" @selected(request('status') === 'active')>Actifs</option>
                    <option value="expiring" @selected(request('status') === 'expiring')>Expire dans 30 jours</option>
                    <option value="expired" @selected(request('status') === 'expired')>Expirés</option>
                </select>
            </div>
            <div class="align-end">
                <button class="btn btn-outline" type="submit">
                    <span class="icon">{!! app_icon('filter') !!}</span> Filtrer
                </button>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Intitulé</th>
                    <th>Fichier</th>
                    <th>Date d’expiration</th>
                    <th>Statut</th>
                    <th>Dernière mise à jour</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $document)
                    @php
                        $days = now('Africa/Abidjan')->startOfDay()->diffInDays($document->expires_at, false);
                        $status = $days < 0 ? 'Expiré' : ($days <= 30 ? 'À surveiller' : 'À jour');
                        $class = $days < 0 ? 'badge-danger' : ($days <= 30 ? 'badge-warning' : 'badge-success');
                    @endphp
                    <tr>
                        <td>{{ $document->document_type }}</td>
                        <td>{{ $document->label ?: '—' }}</td>
                        <td>{{ $document->original_name }}</td>
                        <td>{{ $document->expires_at?->format('d/m/Y') }}</td>
                        <td><span class="badge {{ $class }}">{{ $status }}</span></td>
                        <td>{{ $document->updated_at?->format('d/m/Y H:i') }}</td>
                        <td class="actions-cell">
                            <a class="btn btn-sm btn-outline" href="{{ route('administrative-documents.preview', $document) }}" target="_blank">
                                <span class="icon">{!! app_icon('eye') !!}</span> Lire
                            </a>
                            <a class="btn btn-sm btn-outline" href="{{ route('administrative-documents.download', $document) }}">
                                <span class="icon">{!! app_icon('download') !!}</span> Télécharger
                            </a>
                            @can('update', $document)
                                <a class="btn btn-sm btn-primary" href="{{ route('administrative-documents.edit', $document) }}">
                                    <span class="icon">{!! app_icon('edit') !!}</span> Mettre à jour
                                </a>
                            @endcan
                            @can('delete', $document)
                                <form method="POST" action="{{ route('administrative-documents.destroy', $document) }}" onsubmit="return confirm('Supprimer ce document administratif ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger" type="submit">
                                        <span class="icon">{!! app_icon('trash') !!}</span> Supprimer
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Aucun document administratif enregistré.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $documents->links('partials.pagination') }}
@endsection
