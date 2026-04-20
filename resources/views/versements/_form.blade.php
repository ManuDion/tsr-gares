@php($analysis = $draft['analysis'] ?? null)
@php($analysisError = $draft['analysis_error'] ?? null)
@php($ocrData = data_get($analysis, 'extracted_data', []))
@php($previewLines = data_get($analysis, 'raw_payload.preview_lines', []))
@php($fieldSnippets = data_get($analysis, 'raw_payload.field_snippets', []))
@php($selectedGare = collect($gares)->firstWhere('id', (int) old('gare_id', $ocrData['gare_id'] ?? $versement?->gare_id ?? 0)))
@php($selectedGareLabel = $selectedGare ? ($selectedGare->name.' — '.$selectedGare->city) : ($defaultGareLabel ?? null))

@if(isset($draftToken) && $draftToken)
    <input type="hidden" name="analysis_token" value="{{ $draftToken }}">
@endif

<div class="stack-md">
    @if($analysis)
        <div class="ocr-card">
            <div class="ocr-card-head">
                <div>
                    <strong>Lecture automatique du bordereau</strong>
                    <small>{{ data_get($analysis, 'provider', 'local_tesseract') }}</small>
                </div>
                <span class="badge badge-success">OCR terminé</span>
            </div>

            <div class="ocr-grid">
                <div>
                    <span class="ocr-label">Document</span>
                    <strong>{{ $draft['original_name'] ?? 'Bordereau analysé' }}</strong>
                </div>
                <div>
                    <span class="ocr-label">Modèle détecté</span>
                    <strong>{{ data_get($analysis, 'raw_payload.detected_template', 'Générique') }}</strong>
                </div>
                <div>
                    <span class="ocr-label">Confiance montant</span>
                    <strong>{{ number_format((float) data_get($analysis, 'confidence.amount', 0) * 100, 0) }}%</strong>
                </div>
                <div>
                    <span class="ocr-label">Confiance date</span>
                    <strong>{{ number_format((float) data_get($analysis, 'confidence.operation_date', 0) * 100, 0) }}%</strong>
                </div>
                <div>
                    <span class="ocr-label">Gare suggérée</span>
                    <strong>{{ $ocrData['gare_label'] ?? 'Selon votre périmètre' }}</strong>
                </div>
                <div>
                    <span class="ocr-label">Référence attendue</span>
                    <strong>Nom de l'agence</strong>
                </div>
            </div>
        </div>
    @elseif($analysisError)
        <div class="panel" style="border: 1px solid rgba(234, 179, 8, 0.45); background: rgba(250, 204, 21, 0.08);">
            <h3>Lecture automatique non aboutie</h3>
            <p>{{ $analysisError }}</p>
            <p class="muted">Le bordereau reste bien attaché à cet enregistrement. Vous pouvez poursuivre la saisie manuelle puis valider.</p>
        </div>
    @endif

    <div class="form-grid">
        @unless(auth()->user()->isChefDeGare())
            <div class="col-span-2 field-with-snippet">
                <div>
                    <x-gare-picker
                        :gares="$gares"
                        datalistId="versement-gares"
                        :selectedGareLabel="$selectedGareLabel"
                        :selectedGareId="old('gare_id', $ocrData['gare_id'] ?? $versement?->gare_id ?? null)"
                    />
                </div>
                @if(isset($fieldSnippets['gare_id']))
                    <div class="ocr-snippet">
                        <div class="ocr-snippet-head">
                            <strong>Extrait document</strong>
                            <button class="btn btn-sm btn-outline" type="button" data-copy-text="{{ $fieldSnippets['gare_id'] }}">
                                <span class="icon">{!! app_icon('copy') !!}</span> Copier
                            </button>
                        </div>
                        <pre>{{ $fieldSnippets['gare_id'] }}</pre>
                    </div>
                @endif
            </div>
        @else
            <div class="col-span-2 field-with-snippet">
                <div>
                    <label>Gare affectée</label>
                    <input type="hidden" name="gare_id" value="{{ auth()->user()->gare_id }}">
                    <input type="text" value="{{ auth()->user()->primaryGare?->name }}" disabled>
                    <small>Issue de votre connexion.</small>
                </div>
                @if(isset($fieldSnippets['gare_id']))
                    <div class="ocr-snippet">
                        <div class="ocr-snippet-head">
                            <strong>Extrait document</strong>
                            <button class="btn btn-sm btn-outline" type="button" data-copy-text="{{ $fieldSnippets['gare_id'] }}">
                                <span class="icon">{!! app_icon('copy') !!}</span> Copier
                            </button>
                        </div>
                        <pre>{{ $fieldSnippets['gare_id'] }}</pre>
                    </div>
                @endif
            </div>
        @endunless

        <div class="col-span-2 field-with-snippet">
            <div>
                <label>Date opération</label>
                <input
                    type="date"
                    name="operation_date"
                    value="{{ old('operation_date', $ocrData['operation_date'] ?? $versement?->operation_date?->toDateString() ?? now()->toDateString()) }}"
                    required
                >
                <small>Préremplie après lecture du bordereau.</small>
            </div>

            @if(isset($fieldSnippets['operation_date']))
                <div class="ocr-snippet">
                    <div class="ocr-snippet-head">
                        <strong>Extrait document</strong>
                        <button class="btn btn-sm btn-outline" type="button" data-copy-text="{{ $fieldSnippets['operation_date'] }}">
                            <span class="icon">{!! app_icon('copy') !!}</span> Copier
                        </button>
                    </div>
                    <pre>{{ $fieldSnippets['operation_date'] }}</pre>
                </div>
            @else
                <div class="ocr-snippet ocr-snippet-muted">
                    <div class="ocr-snippet-head">
                        <strong>Extrait document</strong>
                    </div>
                    <p class="muted">Aucun extrait exploitable détecté pour ce champ.</p>
                </div>
            @endif
        </div>

        <div class="col-span-2 field-with-snippet">
            <div>
                <label>Date de la recette</label>
                <input
                    type="date"
                    name="receipt_date"
                    value="{{ old('receipt_date', $versement?->receipt_date?->toDateString() ?? '') }}"
                    required
                >
                <small>Saisie manuelle et validation obligatoire.</small>
            </div>

            @if(isset($fieldSnippets['receipt_date']))
                <div class="ocr-snippet">
                    <div class="ocr-snippet-head">
                        <strong>Extrait document</strong>
                        <button class="btn btn-sm btn-outline" type="button" data-copy-text="{{ $fieldSnippets['receipt_date'] }}">
                            <span class="icon">{!! app_icon('copy') !!}</span> Copier
                        </button>
                    </div>
                    <pre>{{ $fieldSnippets['receipt_date'] }}</pre>
                </div>
            @else
                <div class="ocr-snippet ocr-snippet-muted">
                    <div class="ocr-snippet-head">
                        <strong>Extrait document</strong>
                    </div>
                    <p class="muted">Aucun extrait exploitable détecté pour ce champ.</p>
                </div>
            @endif
        </div>

        <div class="col-span-2 field-with-snippet">
            <div>
                <label>Montant</label>
                <input
                    type="number"
                    name="amount"
                    value="{{ old('amount', $ocrData['amount'] ?? $versement?->amount ?? '') }}"
                    step="0.01"
                    min="0"
                    required
                >
            </div>

            @if(isset($fieldSnippets['amount']))
                <div class="ocr-snippet">
                    <div class="ocr-snippet-head">
                        <strong>Extrait document</strong>
                        <button class="btn btn-sm btn-outline" type="button" data-copy-text="{{ $fieldSnippets['amount'] }}">
                            <span class="icon">{!! app_icon('copy') !!}</span> Copier
                        </button>
                    </div>
                    <pre>{{ $fieldSnippets['amount'] }}</pre>
                </div>
            @else
                <div class="ocr-snippet ocr-snippet-muted">
                    <div class="ocr-snippet-head">
                        <strong>Extrait document</strong>
                    </div>
                    <p class="muted">Aucun extrait exploitable détecté pour ce champ.</p>
                </div>
            @endif
        </div>

        <div class="col-span-2 field-with-snippet">
            <div>
                <label>Banque</label>
                <input
                    type="text"
                    name="bank_name"
                    value="{{ old('bank_name', $ocrData['bank_name'] ?? $versement?->bank_name ?? '') }}"
                >
            </div>

            @if(isset($fieldSnippets['bank_name']))
                <div class="ocr-snippet">
                    <div class="ocr-snippet-head">
                        <strong>Extrait document</strong>
                        <button class="btn btn-sm btn-outline" type="button" data-copy-text="{{ $fieldSnippets['bank_name'] }}">
                            <span class="icon">{!! app_icon('copy') !!}</span> Copier
                        </button>
                    </div>
                    <pre>{{ $fieldSnippets['bank_name'] }}</pre>
                </div>
            @else
                <div class="ocr-snippet ocr-snippet-muted">
                    <div class="ocr-snippet-head">
                        <strong>Extrait document</strong>
                    </div>
                    <p class="muted">Aucun extrait exploitable détecté pour ce champ.</p>
                </div>
            @endif
        </div>

        <div class="col-span-2 field-with-snippet">
            <div>
                <label>Référence (nom de l'agence)</label>
                <input
                    type="text"
                    name="reference"
                    value="{{ old('reference', $ocrData['reference'] ?? $versement?->reference ?? '') }}"
                >
                <small>Pour Coris et Ecobank, ce champ correspond au nom de l'agence.</small>
            </div>

            @if(isset($fieldSnippets['reference']))
                <div class="ocr-snippet">
                    <div class="ocr-snippet-head">
                        <strong>Extrait document</strong>
                        <button class="btn btn-sm btn-outline" type="button" data-copy-text="{{ $fieldSnippets['reference'] }}">
                            <span class="icon">{!! app_icon('copy') !!}</span> Copier
                        </button>
                    </div>
                    <pre>{{ $fieldSnippets['reference'] }}</pre>
                </div>
            @else
                <div class="ocr-snippet ocr-snippet-muted">
                    <div class="ocr-snippet-head">
                        <strong>Extrait document</strong>
                    </div>
                    <p class="muted">Aucun extrait exploitable détecté pour ce champ.</p>
                </div>
            @endif
        </div>

        <div class="col-span-2">
            <label>Description</label>
            <textarea name="description" rows="4">{{ old('description', $versement?->description ?? '') }}</textarea>
        </div>

        @unless(isset($draftToken) && $draftToken)
            <div class="col-span-2">
                <label>Bordereau justificatif obligatoire</label>
                <input type="file" name="bordereau" accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf" capture="environment" required>
                <small>Même en saisie manuelle, le bordereau doit être joint.</small>
            </div>
        @endunless
    </div>

    @if($previewLines)
        <div class="ocr-preview">
            <div class="ocr-snippet-head">
                <strong>Extrait global du document</strong>
                <button class="btn btn-sm btn-outline" type="button" data-copy-text="{{ implode(PHP_EOL, $previewLines) }}">
                    <span class="icon">{!! app_icon('copy') !!}</span> Copier tout
                </button>
            </div>
            <pre>{{ implode(PHP_EOL, $previewLines) }}</pre>
        </div>
    @endif
</div>
