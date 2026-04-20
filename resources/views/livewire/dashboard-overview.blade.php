@php
    $metrics = $this->metrics;
@endphp

@if(($metrics['mode'] ?? 'financial') === 'controleur')
    <div class="stack-lg">
        <div class="stats-grid">
            <x-stat-card title="Total documents" :value="$metrics['documents_total']" meta="Tous les fichiers administratifs" icon="document" />
            <x-stat-card title="Documents actifs" :value="$metrics['documents_active']" meta="Documents non archivés" icon="shield" />
            <x-stat-card title="Zone critique" :value="$metrics['documents_critical']" meta="Expire dans 30 jours" icon="bell" />
        </div>

        <div class="grid-2">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Répartition par type</h2>
                        <p class="text-muted">Nombre de fichiers enregistrés par type de document</p>
                    </div>
                </div>
                <div class="table-wrapper table-plain">
                    <table>
                        <thead>
                            <tr>
                                <th>Type de document</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($metrics['documents_by_type'] as $row)
                                <tr>
                                    <td>{{ $row->document_type }}</td>
                                    <td>{{ $row->total }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2">Aucun document enregistré.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Notifications récentes</h2>
                        <p class="text-muted">Rappels documentaires uniquement</p>
                    </div>
                </div>
                <div class="notification-list">
                    @forelse ($metrics['recent_notifications'] as $notification)
                        <article class="notification-item">
                            <strong>{{ $notification->subject }}</strong>
                            <p>{{ $notification->content }}</p>
                            <small>{{ $notification->concerned_date?->format('d/m/Y') ?? $notification->created_at?->format('d/m/Y H:i') }}</small>
                        </article>
                    @empty
                        <p class="text-muted">Aucune notification documentaire pour le moment.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@else
    @php
        $trend = collect($metrics['trend']);
        $maxValue = max(1, (int) $trend->flatMap(fn ($item) => [$item['recettes'], $item['depenses'], $item['versements']])->max());
        $buildPoints = function (string $key) use ($trend, $maxValue) {
            if ($trend->isEmpty()) {
                return '';
            }

            return $trend->values()->map(function ($item, $index) use ($trend, $maxValue, $key) {
                $x = $trend->count() === 1 ? 20 : 20 + ($index * (260 / max(1, $trend->count() - 1)));
                $y = 120 - (($item[$key] / $maxValue) * 100);
                return round($x, 2).','.round($y, 2);
            })->implode(' ');
        };
        $topRecettes = $metrics['top_recettes'];
        $topDepenses = $metrics['top_depenses'];
        $topSaisie = $metrics['top_saisie'];
    @endphp

    <div class="stack-lg">
        @if ($metrics['user_can_view_all'])
            <div class="panel hero-panel">
                <div class="filters-grid">
                    <div>
                        <label for="start_date">Date début</label>
                        <input id="start_date" type="date" wire:model="start_date">
                    </div>
                    <div>
                        <label for="end_date">Date fin</label>
                        <input id="end_date" type="date" wire:model="end_date">
                    </div>
                    <div>
                        <label for="gare_id">Gare</label>
                        <select id="gare_id" wire:model="gare_id" @disabled(auth()->user()->isChefDeGare())>
                            <option value="">Toutes les gares autorisées</option>
                            @foreach ($this->gares as $gare)
                                <option value="{{ $gare->id }}">{{ $gare->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="align-end">
                        <button class="btn btn-primary" type="button" wire:click="applyFilters">
                            <span class="icon">{!! app_icon('filter') !!}</span>
                            Filtrer
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <div class="stats-grid">
            <x-stat-card title="Total recettes" :value="number_format($metrics['recettes_total'], 0, ',', ' ') . ' FCFA'" :meta="$metrics['user_can_view_all'] ? $metrics['recettes_count'] . ' enregistrements' : $metrics['period_label']" icon="wallet" />
            <x-stat-card title="Total dépenses" :value="number_format($metrics['depenses_total'], 0, ',', ' ') . ' FCFA'" :meta="$metrics['user_can_view_all'] ? $metrics['depenses_count'] . ' enregistrements' : $metrics['period_label']" icon="receipt" />
            <x-stat-card title="Total versements" :value="number_format($metrics['versements_total'], 0, ',', ' ') . ' FCFA'" :meta="$metrics['user_can_view_all'] ? $metrics['versements_count'] . ' enregistrements' : $metrics['period_label']" icon="bank" />
        </div>

        @if ($metrics['missing_yesterday']->isNotEmpty())
            <div class="alert alert-error alert-rich">
                <div class="alert-icon">{!! app_icon('bell') !!}</div>
                <div>
                    <strong>Alertes de non-saisie du jour précédent</strong>
                    <ul>
                        @foreach ($metrics['missing_yesterday'] as $control)
                            <li>{{ $control->gare->name }} : {{ implode(', ', $control->missing_operations ?? []) }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="grid-2">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Évolution des montants</h2>
                        <p class="text-muted">
                            {{ $metrics['user_can_view_all'] ? 'Courbes dynamiques sur la période filtrée' : 'Suivi du mois en cours pour votre périmètre' }}
                        </p>
                    </div>
                </div>
                <div class="chart-card">
                    <svg viewBox="0 0 300 140" class="chart-svg" aria-label="Courbes financières">
                        <line x1="20" y1="120" x2="280" y2="120" class="chart-axis" />
                        <line x1="20" y1="20" x2="20" y2="120" class="chart-axis" />
                        <polyline points="{{ $buildPoints('recettes') }}" class="chart-line chart-line-recettes" />
                        <polyline points="{{ $buildPoints('depenses') }}" class="chart-line chart-line-depenses" />
                        <polyline points="{{ $buildPoints('versements') }}" class="chart-line chart-line-versements" />
                    </svg>
                    <div class="chart-legend">
                        <span><i class="legend-dot recettes"></i> Recettes</span>
                        <span><i class="legend-dot depenses"></i> Dépenses</span>
                        <span><i class="legend-dot versements"></i> Versements</span>
                    </div>
                    <div class="chart-labels">
                        @foreach ($trend as $item)
                            <span>{{ $item['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>{{ $metrics['user_can_view_all'] ? 'Dernières notifications' : 'Notifications récentes' }}</h2>
                        <p class="text-muted">Anomalies, rappels et contrôles journaliers</p>
                    </div>
                </div>
                <div class="notification-list">
                    @forelse ($metrics['recent_notifications'] as $notification)
                        <article class="notification-item">
                            <strong>{{ $notification->subject }}</strong>
                            <p>{{ $notification->content }}</p>
                            <small>{{ $notification->concerned_date?->format('d/m/Y') ?? $notification->created_at?->format('d/m/Y H:i') }}</small>
                        </article>
                    @empty
                        <p class="text-muted">Aucune notification pour le moment.</p>
                    @endforelse
                </div>
            </div>
        </div>

        @if ($metrics['user_can_view_all'])
            <div class="grid-3">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Top 5 en saisie</h2>
                            <p class="text-muted">Les gares les plus régulières sur la période</p>
                        </div>
                    </div>
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
                            <p class="text-muted">Aucune donnée.</p>
                        @endforelse
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Top 5 recettes</h2>
                            <p class="text-muted">Gares les plus performantes sur la période</p>
                        </div>
                    </div>
                    <div class="mini-bars">
                        @forelse ($topRecettes as $row)
                            @php $width = $topRecettes->max('total') > 0 ? ($row->total / $topRecettes->max('total')) * 100 : 0; @endphp
                            <div class="mini-bar-row">
                                <div class="mini-bar-header">
                                    <strong>{{ $row->gare?->name ?? 'Gare' }}</strong>
                                    <span>{{ number_format($row->total, 0, ',', ' ') }} FCFA</span>
                                </div>
                                <div class="mini-bar-track"><div class="mini-bar-fill" style="width: {{ $width }}%"></div></div>
                            </div>
                        @empty
                            <p class="text-muted">Aucune donnée.</p>
                        @endforelse
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Top 5 dépenses</h2>
                            <p class="text-muted">Gares avec le plus de dépenses sur la période</p>
                        </div>
                    </div>
                    <div class="mini-bars">
                        @forelse ($topDepenses as $row)
                            @php $width = $topDepenses->max('total') > 0 ? ($row->total / $topDepenses->max('total')) * 100 : 0; @endphp
                            <div class="mini-bar-row">
                                <div class="mini-bar-header">
                                    <strong>{{ $row->gare?->name ?? 'Gare' }}</strong>
                                    <span>{{ number_format($row->total, 0, ',', ' ') }} FCFA</span>
                                </div>
                                <div class="mini-bar-track"><div class="mini-bar-fill" style="width: {{ $width }}%"></div></div>
                            </div>
                        @empty
                            <p class="text-muted">Aucune donnée.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Derniers contrôles journaliers</h2>
                        <p class="text-muted">Synthèse des conformités et anomalies détectées</p>
                    </div>
                </div>
                <div class="table-wrapper table-plain">
                    <table>
                        <thead>
                            <tr>
                                <th>Date contrôlée</th>
                                <th>Gare</th>
                                <th>Recette</th>
                                <th>Dépense</th>
                                <th>Versement</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($metrics['controls'] as $control)
                                <tr>
                                    <td>{{ $control->concerned_date?->format('d/m/Y') }}</td>
                                    <td>{{ $control->gare?->name }}</td>
                                    <td>{{ $control->has_recette ? 'Oui' : 'Non' }}</td>
                                    <td>{{ $control->has_depense ? 'Oui' : 'Non' }}</td>
                                    <td>{{ $control->has_versement ? 'Oui' : 'Non' }}</td>
                                    <td>
                                        <span class="badge {{ $control->is_compliant ? 'badge-success' : 'badge-danger' }}">
                                            {{ $control->is_compliant ? 'Conforme' : 'Anomalie' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6">Aucun contrôle disponible.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endif
