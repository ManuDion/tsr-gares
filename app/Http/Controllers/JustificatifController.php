<?php

namespace App\Http\Controllers;

use App\Models\Depense;
use App\Models\PieceJustificative;
use App\Models\Recette;
use App\Models\VersementBancaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JustificatifController extends Controller
{
    public function preview(Request $request, string $piece): BinaryFileResponse
    {
        $pieceModel = $this->resolvePieceOrAbort($piece);
        abort_unless($pieceModel !== null, 404);

        $this->assertPieceAccess($request, $pieceModel);

        $resolved = $this->resolvePieceStorage($pieceModel);
        abort_unless($resolved !== null, 404);
        $mimeType = $this->resolveMimeType(
            $pieceModel->mime_type,
            $pieceModel->original_name,
            $resolved['disk'],
            $resolved['path']
        );

        return response()->file(
            Storage::disk($resolved['disk'])->path($resolved['path']),
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="'.$pieceModel->original_name.'"',
                'X-File-Name' => $pieceModel->original_name ?? '',
            ]
        );
    }

    public function download(Request $request, string $piece): StreamedResponse
    {
        $pieceModel = $this->resolvePieceOrAbort($piece);
        abort_unless($pieceModel !== null, 404);

        $this->assertPieceAccess($request, $pieceModel);
        abort_unless($request->user()?->hasGlobalVisibility(), 403);

        $resolved = $this->resolvePieceStorage($pieceModel);
        abort_unless($resolved !== null, 404);

        return Storage::disk($resolved['disk'])->download($resolved['path'], $pieceModel->original_name);
    }

    protected function resolvePieceOrAbort(string $pieceId): ?PieceJustificative
    {
        $piece = PieceJustificative::query()->find($pieceId);
        if ($piece) {
            return $piece;
        }

        $defaultConnection = config('database.default');
        $databaseName = $defaultConnection
            ? config('database.connections.'.$defaultConnection.'.database')
            : null;

        Log::warning('justificatif.preview.piece_not_found', [
            'requested_piece_id' => $pieceId,
            'db_connection' => $defaultConnection,
            'db_database' => $databaseName,
            'pieces_count' => (int) PieceJustificative::query()->count(),
            'pieces_max_id' => (int) (PieceJustificative::query()->max('id') ?? 0),
            'db_server_time' => DB::scalar('SELECT NOW()'),
        ]);

        return null;
    }

    protected function assertPieceAccess(Request $request, PieceJustificative $piece): void
    {
        $context = $this->resolvePieceAccessContext($piece);
        if ($context === null) {
            Log::warning('justificatif.preview.access_context_missing', [
                'piece_id' => $piece->id,
                'attachable_type' => $piece->attachable_type,
                'attachable_id' => $piece->attachable_id,
                'document_type' => $piece->document_type,
                'disk' => $piece->disk,
                'path' => $piece->path,
                'file_name' => $piece->file_name,
            ]);
        }
        abort_unless($context !== null, 404);

        $gareId = $context['gare_id'] ?? null;
        $user = $request->user();
        $scope = $context['service_scope'] ?? null;

        $hasAccess = false;
        if ($user && $gareId) {
            if ($scope) {
                $hasAccess = $user->hasAccessToGare((int) $gareId, $scope);
            } else {
                // Compatibilite historique: certaines anciennes pieces peuvent ne pas avoir de scope explicite.
                $hasAccess = $user->hasAccessToGare((int) $gareId, 'gares')
                    || $user->hasAccessToGare((int) $gareId, 'courrier')
                    || $user->hasAccessToGare((int) $gareId);
            }
        }

        abort_unless($hasAccess, 403);
    }

    /**
     * Resolve gare/scope context for authorization.
     *
     * Handles legacy rows where polymorphic attachable_type may be stale in production.
     *
     * @return array{gare_id:int|null,service_scope:?string}|null
     */
    protected function resolvePieceAccessContext(PieceJustificative $piece): ?array
    {
        try {
            $attachable = $piece->attachable;
        } catch (Throwable) {
            $attachable = null;
        }

        if ($attachable) {
            $gareId = $attachable->gare_id ?? null;
            $scope = is_string($attachable->service_scope ?? null) ? (string) $attachable->service_scope : null;

            return [
                'gare_id' => $gareId ? (int) $gareId : null,
                'service_scope' => $scope,
            ];
        }

        $attachableId = (int) ($piece->attachable_id ?? 0);
        if ($attachableId <= 0) {
            return null;
        }

        $modelClass = match ((string) $piece->document_type) {
            'recette' => Recette::class,
            'depense' => Depense::class,
            'versement_bancaire' => VersementBancaire::class,
            default => null,
        };

        if (! $modelClass) {
            return null;
        }

        $legacyAttachable = $modelClass::query()
            ->select(['id', 'gare_id', 'service_scope'])
            ->find($attachableId);

        if (! $legacyAttachable) {
            return null;
        }

        return [
            'gare_id' => $legacyAttachable->gare_id ? (int) $legacyAttachable->gare_id : null,
            'service_scope' => is_string($legacyAttachable->service_scope ?? null)
                ? (string) $legacyAttachable->service_scope
                : null,
        ];
    }

    protected function resolveMimeType(?string $storedMimeType, ?string $originalName, string $disk, string $path): string
    {
        $mimeType = strtolower(trim((string) $storedMimeType));
        if ($mimeType !== '' && $mimeType !== 'application/octet-stream') {
            return $mimeType;
        }

        $detected = Storage::disk($disk)->mimeType($path);
        if (is_string($detected) && trim($detected) !== '') {
            return strtolower(trim($detected));
        }

        $extension = strtolower((string) pathinfo((string) $originalName, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    /**
     * @return array{disk:string,path:string}|null
     */
    protected function resolvePieceStorage(PieceJustificative $piece): ?array
    {
        $resolved = $piece->resolveStorageLocation();
        if (! $resolved) {
            $candidates = $piece->storageCandidates();
            Log::warning('justificatif.preview.storage_not_found', [
                'piece_id' => $piece->id,
                'disk' => $piece->disk,
                'path' => $piece->path,
                'file_name' => $piece->file_name,
                'document_type' => $piece->document_type,
                'attachable_type' => $piece->attachable_type,
                'attachable_id' => $piece->attachable_id,
                'candidate_disks' => $candidates['disks'],
                'candidate_paths' => $candidates['paths'],
            ]);

            return null;
        }

        if ($piece->disk !== $resolved['disk'] || $piece->path !== $resolved['path']) {
            $piece->forceFill([
                'disk' => $resolved['disk'],
                'path' => $resolved['path'],
            ])->saveQuietly();
        }

        return $resolved;
    }
}
