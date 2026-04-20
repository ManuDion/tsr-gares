<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeVersementBordereauRequest;
use App\Http\Requests\StoreVersementBancaireRequest;
use App\Http\Requests\UnlockVersementBancaireRequest;
use App\Http\Requests\UpdateVersementBancaireRequest;
use App\Models\PieceJustificative;
use App\Models\VersementBancaire;
use App\Models\VersementBancaireHistory;
use App\Services\AccessScopeService;
use App\Services\ActivityLogService;
use App\Services\DocumentAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VersementBancaireController extends Controller
{
    public function __construct(
        protected AccessScopeService $access,
        protected DocumentAnalysisService $analysis,
        protected ActivityLogService $activity
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', VersementBancaire::class);
        $user = $request->user();

        $query = VersementBancaire::query()
            ->with(['gare', 'creator', 'justificatives.latestAnalysis'])
            ->orderByDesc('operation_date')
            ->orderByDesc('id');

        $this->access->scopeForUser($query, $user);

        $query->when($user->canViewAllGares() && $request->filled('gare_id'), fn ($q) => $q->where('gare_id', $request->integer('gare_id')))
            ->when($user->canViewAllGares() && $request->filled('start_date'), fn ($q) => $q->whereDate('operation_date', '>=', $request->date('start_date')))
            ->when($user->canViewAllGares() && $request->filled('end_date'), fn ($q) => $q->whereDate('operation_date', '<=', $request->date('end_date')));

        return view('versements.index', [
            'versements' => $query->paginate(15)->withQueryString(),
            'gares' => $this->access->availableGares($user),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', VersementBancaire::class);

        $draft = $this->getDraft($request->string('draft')->toString());

        return view('versements.create', [
            'gares' => $this->access->availableGares($request->user()),
            'maxSizeKb' => (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120),
            'draft' => $draft,
            'draftToken' => $request->string('draft')->toString() ?: null,
            'defaultGareLabel' => $this->selectedGareLabelFromDraft($draft),
            'manualMode' => $request->boolean('manual'),
        ]);
    }

    public function analyze(AnalyzeVersementBordereauRequest $request): RedirectResponse
    {
        $this->authorize('create', VersementBancaire::class);

        $user = $request->user();
        $file = $request->file('bordereau');
        $disk = env('JUSTIFICATIF_PRIVATE_DISK', 'private');
        $tempPath = $file->store('tmp/versements-ocr', $disk);
        $gares = $this->access->availableGares($user);

        $draft = [
            'temp_path' => $tempPath,
            'disk' => $disk,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'created_at' => now()->toIso8601String(),
        ];

        try {
            $draft['analysis'] = $this->analysis->analyzeFile(
                Storage::disk($disk)->path($tempPath),
                $file->getClientOriginalName(),
                $file->getMimeType(),
                $gares,
                $user->isChefDeGare() ? $user->gare_id : null,
            );

            $status = 'Bordereau analysé. Vérifiez les champs préremplis avant validation.';
            $this->activity->log($user, 'versement_analysis_success', 'Versement OCR', 'Analyse OCR du bordereau réussie.', [
                'gare_id' => $user->isChefDeGare() ? $user->gare_id : null,
                'after' => data_get($draft, 'analysis.extracted_data', []),
                'extra' => [
                    'file' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ],
            ]);
        } catch (\Throwable $exception) {
            $draft['analysis_error'] = "Lecture automatique impossible : {$exception->getMessage()}";
            $status = "Le bordereau a bien été conservé, mais la lecture automatique n'a pas abouti. Vous pouvez poursuivre en saisie manuelle.";

            $this->activity->log($user, 'versement_analysis_failed', 'Versement OCR', 'Analyse OCR du bordereau en échec.', [
                'gare_id' => $user->isChefDeGare() ? $user->gare_id : null,
                'extra' => [
                    'file' => $file->getClientOriginalName(),
                    'message' => $exception->getMessage(),
                ],
            ]);
        }

        $token = Str::uuid()->toString();
        $request->session()->put($this->draftSessionKey($token), $draft);

        return redirect()
            ->route('versements.create', ['draft' => $token])
            ->with('status', $status);
    }

    public function store(StoreVersementBancaireRequest $request): RedirectResponse
    {
        $this->authorize('create', VersementBancaire::class);

        $user = $request->user();
        $data = $request->validated();

        $data['gare_id'] = $this->access->resolveGareIdForCreation($user, $request->integer('gare_id'));
        $data['created_by'] = $user->id;
        $data['updated_by'] = $user->id;

        $versement = VersementBancaire::create($data);

        $draftToken = $request->string('analysis_token')->toString();
        $draft = $this->getDraft($draftToken);

        if ($draft) {
            $piece = $this->attachDraftPiece($versement, $draft, $user->id);

            if (! empty($draft['analysis'])) {
                $this->analysis->persistAnalysis($piece, $draft['analysis']);
            }

            $request->session()->forget($this->draftSessionKey($draftToken));
        } elseif ($request->hasFile('bordereau')) {
            $piece = $this->attachUploadedPiece($versement, $request->file('bordereau'), $user->id);
            $this->analysis->analyze($piece);
        }

        $this->activity->log($user, 'versement_created', $versement, 'Création d\'un versement bancaire.', [
            'gare_id' => $versement->gare_id,
            'after' => $versement->only(['gare_id', 'operation_date', 'receipt_date', 'amount', 'reference', 'bank_name', 'description']),
        ]);

        return redirect()->route('versements.index')->with('status', 'Versement bancaire enregistré.');
    }

    public function edit(VersementBancaire $versement): View
    {
        $this->authorize('update', $versement);

        return view('versements.edit', [
            'versement' => $versement->load(['gare', 'histories.modifier', 'justificatives.latestAnalysis']),
            'gares' => $this->access->availableGares(auth()->user()),
        ]);
    }

    public function update(UpdateVersementBancaireRequest $request, VersementBancaire $versement): RedirectResponse
    {
        $this->authorize('update', $versement);

        $before = $versement->only([
            'operation_date',
            'receipt_date',
            'amount',
            'reference',
            'bank_name',
            'description',
            'gare_id',
        ]);

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        if (! $request->user()->isChefDeGare()) {
            $data['gare_id'] = $request->integer('gare_id', $versement->gare_id);
        }

        $versement->update($data);

        $after = $versement->fresh()->only([
            'operation_date',
            'receipt_date',
            'amount',
            'reference',
            'bank_name',
            'description',
            'gare_id',
        ]);

        $hasFieldChanges = $this->hasMeaningfulChanges($before, $after);

        if ($hasFieldChanges) {
            VersementBancaireHistory::create([
                'versement_bancaire_id' => $versement->id,
                'modified_by' => $request->user()->id,
                'before' => $before,
                'after' => $after,
                'comment' => $request->string('history_comment')->toString() ?: 'Modification de versement',
            ]);

            $this->activity->log($request->user(), 'versement_updated', $versement, 'Modification d\'un versement bancaire.', [
                'gare_id' => $versement->gare_id,
                'before' => $before,
                'after' => $after,
                'notes' => $request->string('history_comment')->toString() ?: null,
            ]);
        }

        $status = $hasFieldChanges ? 'Versement modifié.' : 'Aucune modification détectée sur le versement.';

        return redirect()->route('versements.index')->with('status', $status);
    }

    public function unlock(UnlockVersementBancaireRequest $request, VersementBancaire $versement): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isResponsable(), 403);

        $before = $versement->only(['force_unlocked_until', 'unlock_reason', 'unlocked_by']);

        $versement->update([
            'force_unlocked_until' => now()->addHours(24),
            'unlock_reason' => $request->validated('unlock_reason'),
            'unlocked_by' => $request->user()->id,
        ]);

        $this->activity->log($request->user(), 'versement_unlocked', $versement, 'Déverrouillage superviseur d\'un versement.', [
            'gare_id' => $versement->gare_id,
            'before' => $before,
            'after' => $versement->fresh()->only(['force_unlocked_until', 'unlock_reason', 'unlocked_by']),
        ]);

        return back()->with('status', 'Versement déverrouillé pour 24h.');
    }

    protected function hasMeaningfulChanges(array $before, array $after): bool
    {
        return collect($before)->keys()->contains(function ($key) use ($before, $after) {
            return $this->normalizeHistoryValue($before[$key] ?? null) !== $this->normalizeHistoryValue($after[$key] ?? null);
        });
    }

    protected function normalizeHistoryValue(mixed $value): string
    {
        if (is_array($value)) {
            ksort($value);

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return trim((string) $value);
    }

    protected function attachDraftPiece(VersementBancaire $versement, array $draft, int $userId): PieceJustificative
    {
        $disk = $draft['disk'] ?? env('JUSTIFICATIF_PRIVATE_DISK', 'private');
        $tempPath = $draft['temp_path'];
        $finalPath = 'justificatifs/versements/'.now()->format('Y/m').'/'.basename($tempPath);

        Storage::disk($disk)->move($tempPath, $finalPath);

        return $versement->justificatives()->create([
            'document_type' => 'versement_bancaire',
            'original_name' => $draft['original_name'],
            'file_name' => basename($finalPath),
            'mime_type' => $draft['mime_type'] ?: 'application/pdf',
            'size' => $draft['size'] ?? 0,
            'disk' => $disk,
            'path' => $finalPath,
            'uploaded_by' => $userId,
            'uploaded_at' => now(),
        ]);
    }

    protected function attachUploadedPiece(VersementBancaire $versement, $file, int $userId): PieceJustificative
    {
        $disk = env('JUSTIFICATIF_PRIVATE_DISK', 'private');
        $path = $file->store('justificatifs/versements/'.now()->format('Y/m'), $disk);

        return $versement->justificatives()->create([
            'document_type' => 'versement_bancaire',
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'mime_type' => $file->getMimeType() ?: 'application/pdf',
            'size' => $file->getSize(),
            'disk' => $disk,
            'path' => $path,
            'uploaded_by' => $userId,
            'uploaded_at' => now(),
        ]);
    }

    protected function getDraft(?string $token): ?array
    {
        if (! $token) {
            return null;
        }

        return session($this->draftSessionKey($token));
    }

    protected function draftSessionKey(string $token): string
    {
        return 'versement_ocr_drafts.'.$token;
    }

    protected function selectedGareLabelFromDraft(?array $draft): ?string
    {
        $label = data_get($draft, 'analysis.extracted_data.gare_label');

        return $label ?: null;
    }
}
