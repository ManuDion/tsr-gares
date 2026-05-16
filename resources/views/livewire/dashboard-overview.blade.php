@php
    $metrics = $this->metrics;
    $module = $metrics['module'] ?? null;
    $fmt = fn ($value) => str_replace(' ', "\u{00A0}", number_format((float) $value, 0, '', ' '));
    $weeklyTitle = match ($metrics['period'] ?? 'month') {
        'today' => "Comparatif journalier (aujourd'hui)",
        'week' => 'Comparatif quotidien des 7 derniers jours',
        default => 'Comparatif hebdomadaire du mois en cours',
    };
    $weeklySub = match ($metrics['period'] ?? 'month') {
        'today' => "Aujourd'hui",
        'week' => 'J-6 a J',
        default => 'Semaine S1 a S4',
    };
    $useEntryCounts = (bool) ($metrics['use_entry_counts'] ?? false);
@endphp

@if(($metrics['mode'] ?? 'financial') === 'controleur')
    <div class="stack-lg">
        <div class="stats-grid">
            <x-stat-card title="Total documents" :value="$metrics['documents_total']" meta="Tous les fichiers administratifs" icon="document" />
            <x-stat-card title="Documents actifs" :value="$metrics['documents_active']" meta="Documents non archives" icon="shield" />
            <x-stat-card title="Zone critique" :value="$metrics['documents_critical']" meta="Expire dans 30 jours" icon="bell" />
        </div>

        <div class="grid-2">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Repartition par type</h2>
                        <p class="text-muted">Nombre de fichiers enregistres par type de document</p>
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
                                <tr><td colspan="2">Aucun document enregistre.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Notifications recentes</h2>
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
            <x-stat-card title="Agents enregistres" :value="$metrics['employees_total']" meta="Dossiers du personnel" icon="users" />
            <x-stat-card title="Agents actifs" :value="$metrics['employees_active']" meta="Statut actif" icon="shield" />
            <x-stat-card title="Comptes a activer" :value="$metrics['accounts_pending_activation']" meta="Espaces personnels en attente" icon="bell" />
            <x-stat-card title="Pieces RH" :value="$metrics['documents_total']" meta="Documents centralises" icon="document" />
        </div>

        <div class="grid-2">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Repartition par service</h2>
                        <p class="text-muted">Base preparatoire du module RH</p>
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
                                    <td>{{ $row->department?->name ?? 'Non renseigne' }}</td>
                                    <td>{{ $row->total }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2">Aucun dossier RH enregistre.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Derniers dossiers crees</h2>
                        <p class="text-muted">Creations recentes du service RH</p>
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
                                    <td>{{ $employee->department?->name ?? '-' }}</td>
                                    <td>{{ $employee->employment_status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3">Aucun dossier recent.</td></tr>
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
        $isCourrier = (bool) ($metrics['is_courrier'] ?? false);
    @endphp

    <div class="stack-lg">
        <div class="panel hero-panel">
            <div class="filters-grid">
                <div>
                    <label for="period">Periode</label>
                    <select id="period" wire:model.live="period">
                        @foreach($metrics['period_options'] ?? [] as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="start_date">Date debut</label>
                    <input id="start_date" type="date" wire:model.live="start_date">
                </div>
                <div>
                    <label for="end_date">Date fin</label>
                    <input id="end_date" type="date" wire:model.live="end_date">
                </div>
                @if ($metrics['show_gare_filter'])
                    <div>
                        <label for="gare_id">Gare</label>
                        <select id="gare_id" wire:model.live="gare_id">
                            <option value="">Toutes les gares</option>
                            @foreach($this->gares as $gare)
                                <option value="{{ $gare->id }}">{{ $gare->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="stats-grid">
                @if($useEntryCounts)
                    <x-stat-card title="Recettes enregistrees" :value="$metrics['recettes_count']" meta="Nombre d'enregistrements - {{ $metrics['period_label'] }}" icon="wallet" />
                    <x-stat-card title="Depenses enregistrees" :value="$metrics['depenses_count']" meta="Nombre d'enregistrements - {{ $metrics['period_label'] }}" icon="receipt" />
                    <x-stat-card title="Versements enregistres" :value="$metrics['versements_count']" meta="Nombre d'enregistrements - {{ $metrics['period_label'] }}" icon="bank" />
                @else
                    <x-stat-card title="Total recettes" :value="$fmt($metrics['recettes_total'])" meta="{{ $serviceTitle }} - {{ $metrics['period_label'] }}" icon="wallet" />
                    <x-stat-card title="Total depenses" :value="$fmt($metrics['depenses_total'])" meta="{{ $serviceTitle }} - {{ $metrics['period_label'] }}" icon="receipt" />
                    <x-stat-card title="Total versements" :value="$fmt($metrics['versements_total'])" meta="{{ $serviceTitle }} - {{ $metrics['period_label'] }}" icon="bank" />
                @endif
            </div>
        </div>

        @if($useEntryCounts)
            <div class="grid-2">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Role du verificateur</h2>
                            <p class="text-muted">Controle de conformite, de completude et d'exactitude des informations saisies.</p>
                        </div>
                    </div>
                    <p class="text-muted">
                        Utilisez les filtres ci-dessus pour concentrer la verification par periode et par gare.
                    </p>
                </div>
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Notifications metier</h2>
                            <p class="text-muted">Messages lies au module actif</p>
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
                            <p class="text-muted">Aucune notification recente.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Alertes metier</h2>
                        <p class="text-muted">Non-saisies detectees sur la journee precedente</p>
                    </div>
                </div>
                <div class="notification-list">
                    @forelse($metrics['missing_yesterday'] as $control)
                        <article class="notification-item">
                            <strong>{{ $control->gare?->name }}</strong>
                            <p>Operations manquantes : {{ collect($control->missing_operations ?? [])->map(fn($item) => $item === 'validation_caissier' ? 'validation caissier' : str_replace('_', ' ', $item))->implode(', ') }}</p>
                            <small>{{ $control->concerned_date?->format('d/m/Y') }}</small>
                        </article>
                    @empty
                        <p class="text-muted">Aucune alerte de non-saisie pour la veille.</p>
                    @endforelse
                </div>
            </div>
        @else
        <div class="grid-2">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Detail des types de recettes</h2>
                        <p class="text-muted">Repartition globale pour la periode selectionnee</p>
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
                                <tr>
                                    <td>Recette courrier</td>
                                    <td>{{ number_format($metrics['recette_breakdown_totals']->total_amount ?? 0, 0, '', ' ') }}</td>
                                </tr>
                            @else
                                <tr><td>Tickets inter</td><td>{{ number_format($metrics['recette_breakdown_totals']->ticket_inter_total ?? 0, 0, '', ' ') }}</td></tr>
                                <tr><td>Tickets national</td><td>{{ number_format($metrics['recette_breakdown_totals']->ticket_national_total ?? 0, 0, '', ' ') }}</td></tr>
                                <tr><td>Bagages inter</td><td>{{ number_format($metrics['recette_breakdown_totals']->bagage_inter_total ?? 0, 0, '', ' ') }}</td></tr>
                                <tr><td>Bagages national</td><td>{{ number_format($metrics['recette_breakdown_totals']->bagage_national_total ?? 0, 0, '', ' ') }}</td></tr>
                                <tr>
                                    <td><strong>Recette inter</strong></td>
                                    <td><strong>{{ number_format(($metrics['recette_breakdown_totals']->ticket_inter_total ?? 0) + ($metrics['recette_breakdown_totals']->bagage_inter_total ?? 0), 0, '', ' ') }}</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Recette nationale</strong></td>
                                    <td><strong>{{ number_format(($metrics['recette_breakdown_totals']->ticket_national_total ?? 0) + ($metrics['recette_breakdown_totals']->bagage_national_total ?? 0), 0, '', ' ') }}</strong></td>
                                </tr>
                                <tr><td><strong>Total recettes</strong></td><td><strong>{{ number_format($metrics['recette_breakdown_totals']->total_amount ?? 0, 0, '', ' ') }}</strong></td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Notifications recentes</h2>
                        <p class="text-muted">Messages lies au module actif</p>
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
                        <p class="text-muted">Aucune notification recente.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid-2">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Evolution des montants</h2>
                        <p class="text-muted">Courbe evolutive jour par jour (1 a 31)</p>
                    </div>
                </div>

                <div class="chart-card">
                    <svg viewBox="0 0 340 170" class="chart-svg" aria-label="Courbe d'evolution des montants">
                        <line x1="20" y1="140" x2="320" y2="140" class="chart-axis" />
                        <line x1="20" y1="20" x2="20" y2="140" class="chart-axis" />
                        <polyline class="chart-line chart-line-recettes" points="{{ $metrics['trend_chart']['recettes'] ?? '' }}"></polyline>
                        <polyline class="chart-line chart-line-depenses" points="{{ $metrics['trend_chart']['depenses'] ?? '' }}"></polyline>
                        <polyline class="chart-line chart-line-versements" points="{{ $metrics['trend_chart']['versements'] ?? '' }}"></polyline>
                    </svg>
                    <div class="chart-legend">
                        <span><i class="legend-dot recettes"></i> Recettes</span>
                        <span><i class="legend-dot depenses"></i> Depenses</span>
                        <span><i class="legend-dot versements"></i> Versements</span>
                    </div>
                </div>

                <div class="panel-header panel-header-top">
                    <div>
                        <h2>{{ $weeklyTitle }}</h2>
                        <p class="text-muted">{{ $weeklySub }}</p>
                    </div>
                </div>
                <div class="table-wrapper table-plain weekly-comparison-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Periode</th>
                                @if($isCourrier)
                                    <th>Recettes courrier</th>
                                @else
                                    <th>Recette inter</th>
                                    <th>Recette nationale</th>
                                    <th>Recette totale</th>
                                @endif
                                <th>Depenses</th>
                                <th>Versements</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($metrics['weekly_comparison'] as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    @if($isCourrier)
                                        <td class="amount-nowrap">{{ $fmt($row['recettes_total']) }}</td>
                                    @else
                                        <td class="amount-nowrap">{{ $fmt($row['recettes_inter']) }}</td>
                                        <td class="amount-nowrap">{{ $fmt($row['recettes_national']) }}</td>
                                        <td class="amount-nowrap">{{ $fmt($row['recettes_total']) }}</td>
                                    @endif
                                    <td class="amount-nowrap">{{ $fmt($row['depenses']) }}</td>
                                    <td class="amount-nowrap">{{ $fmt($row['versements']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $isCourrier ? 4 : 6 }}">Aucune donnee hebdomadaire.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Alertes metier</h2>
                        <p class="text-muted">Non-saisies detectees sur la journee precedente</p>
                    </div>
                </div>
                <div class="notification-list">
                    @forelse($metrics['missing_yesterday'] as $control)
                        <article class="notification-item">
                            <strong>{{ $control->gare?->name }}</strong>
                            <p>Operations manquantes : {{ collect($control->missing_operations ?? [])->map(fn($item) => $item === 'validation_caissier' ? 'validation caissier' : str_replace('_', ' ', $item))->implode(', ') }}</p>
                            <small>{{ $control->concerned_date?->format('d/m/Y') }}</small>
                        </article>
                    @empty
                        <p class="text-muted">Aucune alerte de non-saisie pour la veille.</p>
                    @endforelse
                </div>
            </div>
        </div>

        @if($metrics['show_global_sections'])
            <div class="grid-2">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Top gares en recettes</h2>
                            <p class="text-muted">Periode selectionnee</p>
                        </div>
                    </div>
                    <div class="table-wrapper table-plain">
                        <table>
                            <thead>
                                <tr>
                                    <th>Gare</th>
                                    <th>Total FCFA</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($metrics['top_recettes'] as $row)
                                    <tr>
                                        <td>{{ $row->gare?->name }}</td>
                                        <td>{{ number_format($row->total, 0, '', ' ') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2">Aucune donnee.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Top gares en depenses</h2>
                            <p class="text-muted">Periode selectionnee</p>
                        </div>
                    </div>
                    <div class="table-wrapper table-plain">
                        <table>
                            <thead>
                                <tr>
                                    <th>Gare</th>
                                    <th>Total FCFA</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($metrics['top_depenses'] as $row)
                                    <tr>
                                        <td>{{ $row->gare?->name }}</td>
                                        <td>{{ number_format($row->total, 0, '', ' ') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2">Aucune donnee.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Detail des types de recettes par gare (Top 5)</h2>
                        <p class="text-muted">Top 5 des gares sur la periode selectionnee</p>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            @if($isCourrier)
                                <tr>
                                    <th>Gare</th>
                                    <th>Total recettes</th>
                                </tr>
                            @else
                                <tr>
                                    <th>Gare</th>
                                    <th>Tickets inter</th>
                                    <th>Tickets national</th>
                                    <th>Bagages inter</th>
                                    <th>Bagages national</th>
                                    <th>Total recettes</th>
                                </tr>
                            @endif
                        </thead>
                        <tbody>
                            @forelse($metrics['recette_breakdown_by_gare'] as $row)
                                <tr>
                                    <td>{{ $row->gare?->name }}</td>
                                    @if($isCourrier)
                                        <td>{{ number_format($row->total_amount, 0, '', ' ') }}</td>
                                    @else
                                        <td>{{ number_format($row->ticket_inter_total, 0, '', ' ') }}</td>
                                        <td>{{ number_format($row->ticket_national_total, 0, '', ' ') }}</td>
                                        <td>{{ number_format($row->bagage_inter_total, 0, '', ' ') }}</td>
                                        <td>{{ number_format($row->bagage_national_total, 0, '', ' ') }}</td>
                                        <td>{{ number_format($row->total_amount, 0, '', ' ') }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $isCourrier ? 2 : 6 }}">Aucune donnee a afficher.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
        @endif
    </div>
@endif
