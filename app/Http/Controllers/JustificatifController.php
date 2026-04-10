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

        abort_unless($piece->exists(), 404);

        return response()->file(
            Storage::disk($piece->disk)->path($piece->path),
            [
                'Content-Type' => $piece->mime_type ?: 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$piece->original_name.'"',
            ]
        );
    }

    public function download(Request $request, PieceJustificative $piece): StreamedResponse
    {
        $this->assertPieceAccess($request, $piece);

        abort_unless($piece->exists(), 404);

        return Storage::disk($piece->disk)->download($piece->path, $piece->original_name);
    }

    protected function assertPieceAccess(Request $request, PieceJustificative $piece): void
    {
        $attachable = $piece->attachable;

        abort_unless($attachable, 404);

        $gareId = $attachable->gare_id ?? null;

        abort_unless($gareId && $request->user()->hasAccessToGare($gareId), 403);
    }
}
