<?php

namespace App\Http\Controllers;

use App\Models\PieceJustificative;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JustificatifController extends Controller
{
    public function preview(Request $request, PieceJustificative $piece): BinaryFileResponse
    {
        $this->assertPieceAccess($request, $piece);

        $resolved = $this->resolvePieceStorage($piece);
        abort_unless($resolved !== null, 404);
        $mimeType = $this->resolveMimeType(
            $piece->mime_type,
            $piece->original_name,
            $resolved['disk'],
            $resolved['path']
        );

        return response()->file(
            Storage::disk($resolved['disk'])->path($resolved['path']),
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="'.$piece->original_name.'"',
                'X-File-Name' => $piece->original_name ?? '',
            ]
        );
    }

    public function download(Request $request, PieceJustificative $piece): StreamedResponse
    {
        $this->assertPieceAccess($request, $piece);
        abort_unless($request->user()?->hasGlobalVisibility(), 403);

        $resolved = $this->resolvePieceStorage($piece);
        abort_unless($resolved !== null, 404);

        return Storage::disk($resolved['disk'])->download($resolved['path'], $piece->original_name);
    }

    protected function assertPieceAccess(Request $request, PieceJustificative $piece): void
    {
        $attachable = $piece->attachable;

        abort_unless($attachable, 404);

        $gareId = $attachable->gare_id ?? null;
        $user = $request->user();
        $scope = is_string($attachable->service_scope ?? null) ? (string) $attachable->service_scope : null;

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
