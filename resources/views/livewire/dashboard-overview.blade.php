@php
    $metrics = $this->metrics;
    $module = $metrics['module'] ?? null;
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
@elseif(($metrics['mode'] ?? '') === 'rh')
    <div class="stack-lg">
        <div class="stats-grid">
            <x-stat-card title="Agents enregistrés" :value="$metrics['employees_total']" meta="Dossiers du personnel" icon="users" />
            <x-stat-card title="Agents actifs" :value="$metrics['employees_active']" meta="Statut actif" icon="shield" />
            <x-stat-card title="Comptes à activer" :value="$metrics['accounts_pending_activation']" meta="Espaces personnels en attente" icon="bell" />
            <x-stat-card title="Pièces RH" :value="$metrics['documents_total']" meta="Documents centralisés" icon="document" />
        </div>

        <div class="grid-2">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Répartition par service</h2>
                        <p class="text-muted">Base préparatoire du module RH</p>
                    </div>
                </div>
                <div class="table-wrapper table-plain">
                    <table>
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($metrics['employees_by_department'] as $row)
                                <tr>
                                    <td>{{ $row->department?->name ?? 'Non renseigné' }}</td>
                                    <td>{{ $row->total }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2">Aucun dossier RH enregistré.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Derniers dossiers créés</h2>
                        <p class="text-muted">Créations récentes du service RH</p>
                    </div>
                </div>
                <div class="table-wrapper table-plain">
                    <table>
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Service</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($metrics['recent_employees'] as $employee)
                                <tr>
                                    <td>{{ $employee->full_name }}</td>
                                    <td>{{ $employee->department?->name ?? '—' }}</td>
                                    <td>{{ $employee->employment_status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3">Aucun dossier récent.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@else
    @php
        $serviceTitle = ($module?->value ?? 'gares') === 'courrier' ? 'service courrier' : 'service de gestion des gares';
        $isCourrier = ($module?->value ?? 'gares') === 'courrier';
    @endphp
    <div class="stack-lg">
        <div class="panel hero-panel">
            @if ($metrics['user_can_view_all'])
                <div class="filters-grid">
                    <div>
                        <label for="start_date">Date début</label>
                        <input id="start_date" type="date" wire:model.live="start_date">
                    </div>
                    <div>
                        <label for="end_date">Date fin</label>
                        <input id="end_date" type="date" wire:model.live="end_date">
                    </div>
                    <div>
                        <label for="gare_id">Gare</label>
                        <select id="gare_id" wire:model.live="gare_id">
                            <option value="">Toutes les gares</option>
                            @foreach($this->gares as $gare)
                                <option value="{{ $gare->id }}">{{ $gare->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif

            <div class="stats-grid">
                <x-stat-card title="Total recettes" :value="number_format($metrics['recettes_total'], 0, ',', ' ')" meta="{{ $serviceTitle }} · {{ $metrics['period_label'] }}" icon="wallet" />
                <x-stat-card title="Total dépenses" :value="number_format($metrics['depenses_total'], 0, ',', ' ')" meta="{{ $serviceTitle }} · {{ $metrics['period_label'] }}" icon="receipt" />
                <x-stat-card title="Total versements" :value="number_format($metrics['versements_total'], 0, ',', ' ')" meta="{{ $serviceTitle }} · {{ $metrics['period_label'] }}" icon="bank" />
            </div>
        </div>

        <div class="grid-2">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>{{ $isCourrier ? 'Recettes courrier' : 'Détail des types de recettes' }}</h2>
                        <p class="text-muted">{{ $isCourrier ? 'Le service courrier utilise une recette unique par jour.' : 'Répartition globale pour la période sélectionnée' }}</p>
                    </div>
                </div>
                <div class="table-wrapper table-plain">
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Total FCFA</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($isCourrier)
                                <tr><td>Recette unique courrier</td><td>{{ number_format($metrics['recette_breakdown_totals']->total_amount ?? 0, 0, ',', ' ') }}</td></tr>
                            @else
                                <tr><td>Tickets inter</td><td>{{ number_format($metrics['recette_breakdown_totals']->ticket_inter_total ?? 0, 0, ',', ' ') }}</td></tr>
                                <tr><td>Tickets national</td><td>{{ number_format($metrics['recette_breakdown_totals']->ticket_national_total ?? 0, 0, ',', ' ') }}</td></tr>
                                <tr><td>Bagages inter</td><td>{{ number_format($metrics['recette_breakdown_totals']->bagage_inter_total ?? 0, 0, ',', ' ') }}</td></tr>
                                <tr><td>Bagages national</td><td>{{ number_format($metrics['recette_breakdown_totals']->bagage_national_total ?? 0, 0, ',', ' ') }}</td></tr>
                                <tr><td><strong>Total recettes</strong></td><td><strong>{{ number_format($metrics['recette_breakdown_totals']->total_amount ?? 0, 0, ',', ' ') }}</strong></td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Notifications récentes</h2>
                        <p class="text-muted">Messages liés au module actif</p>
                    </div>
                </div>
                <div class="notification-list">
                    @forelse ($metrics['recent_notifications'] as $notification)
                        <article class="notification-item">
                            <strong>{{ $notification->subject }}</strong>
                            <p>{{ $notification->content }}</p>
                            <small>{{ $notification->created_at?->format('d/m/Y H:i') }}</small>
                        </article>
                    @empty
                        <p class="text-muted">Aucune notification récente.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid-2">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Évolution des montants</h2>
                        <p class="text-muted">Comparatif hebdomadaire du mois en cours (S1 à S4)</p>
                    </div>
                </div>

                <div class="trend-chart-card">
                    <svg viewBox="0 0 300 160" class="trend-chart" aria-label="Graphique d'évolution des montants">
                        <line x1="20" y1="140" x2="280" y2="140" class="chart-axis" />
                        <line x1="20" y1="20" x2="20" y2="140" class="chart-axis" />
                        <polyline class="chart-line chart-line-recettes" points="{{ $metrics['trend_chart']['recettes'] ?? '' }}"></polyline>
                        <polyline class="chart-line chart-line-depenses" points="{{ $metrics['trend_chart']['depenses'] ?? '' }}"></polyline>
                        <polyline class="chart-line chart-line-versements" points="{{ $metrics['trend_chart']['versements'] ?? '' }}"></polyline>
                        @foreach($metrics['trend'] as $index => $row)
                            @php($x = 20 + ($index * (260 / max(1, (count($metrics['trend']) - 1)))))
                            <text x="{{ $x }}" y="154" text-anchor="middle" class="chart-label">{{ $row['label'] }}</text>
                        @endforeach
                    </svg>

                    <div class="chart-legend">
                        <span><i class="legend-dot legend-dot-recettes"></i> Recettes</span>
                        <span><i class="legend-dot legend-dot-depenses"></i> Dépenses</span>
                        <span><i class="legend-dot legend-dot-versements"></i> Versements</span>
                    </div>
                </div>

                <div class="table-wrapper table-plain">
                    <table>
                        <thead>
                            <tr>
                                <th>Semaine</th>
                                <th>Recettes</th>
                                <th>Dépenses</th>
                                <th>Versements</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($metrics['trend'] as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td>{{ number_format($row['recettes'], 0, ',', ' ') }}</td>
                                    <td>{{ number_format($row['depenses'], 0, ',', ' ') }}</td>
                                    <td>{{ number_format($row['versements'], 0, ',', ' ') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4">Aucune donnée de période.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Alertes métier</h2>
                        <p class="text-muted">Non-saisies détectées sur la journée précédente</p>
                    </div>
                </div>
                <div class="notification-list">
                    @forelse($metrics['missing_yesterday'] as $control)
                        <article class="notification-item">
                            <strong>{{ $control->gare?->name }}</strong>
                            <p>Opérations manquantes : {{ collect($control->missing_operations ?? [])->map(fn($item) => str_replace('_', ' ', $item))->implode(', ') }}</p>
                            <small>{{ $control->concerned_date?->format('d/m/Y') }}</small>
                        </article>
                    @empty
                        <p class="text-muted">Aucune alerte de non-saisie pour la veille.</p>
                    @endforelse
                </div>
            </div>
        </div>

        @if($metrics['user_can_view_all'])
            <div class="grid-2">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Top gares en recettes</h2>
                            <p class="text-muted">Période sélectionnée</p>
                        </div>
                    </div>
                    <div class="table-wrapper table-plain">
                        <table>
                            <thead>
                                <tr><th>Gare</th><th>Total FCFA</th></tr>
                            </thead>
                            <tbody>
                                @forelse($metrics['top_recettes'] as $row)
                                    <tr><td>{{ $row->gare?->name }}</td><td>{{ number_format($row->total, 0, ',', ' ') }}</td></tr>
                                @empty
                                    <tr><td colspan="2">Aucune donnée.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Top gares en dépenses</h2>
                            <p class="text-muted">Période sélectionnée</p>
                        </div>
                    </div>
                    <div class="table-wrapper table-plain">
                        <table>
                            <thead>
                                <tr><th>Gare</th><th>Total FCFA</th></tr>
                            </thead>
                            <tbody>
                                @forelse($metrics['top_depenses'] as $row)
                                    <tr><td>{{ $row->gare?->name }}</td><td>{{ number_format($row->total, 0, ',', ' ') }}</td></tr>
                                @empty
                                    <tr><td colspan="2">Aucune donnée.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @unless($isCourrier)
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Détail des types de recettes par gare</h2>
                            <p class="text-muted">Affichage par gare et par période</p>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Gare</th>
                                    <th>Tickets inter</th>
                                    <th>Tickets national</th>
                                    <th>Bagages inter</th>
                                    <th>Bagages national</th>
                                    <th>Total recettes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($metrics['recette_breakdown_by_gare'] as $row)
                                    <tr>
                                        <td>{{ $row->gare?->name }}</td>
                                        <td>{{ number_format($row->ticket_inter_total, 0, ',', ' ') }}</td>
                                        <td>{{ number_format($row->ticket_national_total, 0, ',', ' ') }}</td>
                                        <td>{{ number_format($row->bagage_inter_total, 0, ',', ' ') }}</td>
                                        <td>{{ number_format($row->bagage_national_total, 0, ',', ' ') }}</td>
                                        <td>{{ number_format($row->total_amount, 0, ',', ' ') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6">Aucune donnée à afficher.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endunless
        @endif
    </div>
@endif
