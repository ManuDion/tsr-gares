<?php

namespace App\Http\Controllers;

use App\Models\Depense;
use App\Models\Gare;
use App\Models\PieceJustificative;
use App\Models\Recette;
use App\Models\User;
use App\Models\VersementBancaire;
use App\Support\ModuleContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class BatchJustificatifController extends Controller
{
    private const TYPES = [
        'recettes' => [
            'title' => 'Justificatifs recettes',
            'document_type' => 'recette',
            'attachable' => Recette::class,
        ],
        'depenses' => [
            'title' => 'Justificatifs depense',
            'document_type' => 'depense',
            'attachable' => Depense::class,
        ],
        'versements' => [
            'title' => 'Justificatifs versement',
            'document_type' => 'versement_bancaire',
            'attachable' => VersementBancaire::class,
        ],
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->hasGlobalVisibility(), 403);

        $module = ModuleContext::fromRequest($request, $user);

        $activeTab = $request->string('tab')->toString();
        if (! array_key_exists($activeTab, self::TYPES)) {
            $activeTab = 'recettes';
        }

        $gares = Gare::query()
            ->where('is_active', true)
            ->where('is_virtual', false)
            ->orderBy('name')
            ->get(['id', 'name']);

        $chefsByGare = User::query()
            ->where('role', 'chef_de_gare')
            ->whereIn('gare_id', $gares->pluck('id')->all())
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['gare_id', 'name', 'phone'])
            ->groupBy('gare_id')
            ->map(fn (Collection $rows) => $rows->first());

        $rows = $gares->map(function (Gare $gare) use ($chefsByGare): array {
            $chef = $chefsByGare->get($gare->id);

            return [
                'gare' => $gare,
                'chef_name' => $chef?->name ?: '-',
                'chef_phone' => $chef?->phone ?: '-',
            ];
        });

        return view('justificatifs-batch.index', [
            'module' => $module,
            'activeTab' => $activeTab,
            'tabs' => self::TYPES,
            'rows' => $rows,
        ]);
    }

    public function periodForm(Request $request, string $type, Gare $gare): View
    {
        $user = $request->user();
        abort_unless($user?->hasGlobalVisibility(), 403);
        abort_unless(array_key_exists($type, self::TYPES), 404);
        abort_unless($gare->is_active && ! $gare->is_virtual, 404);

        $module = ModuleContext::fromRequest($request, $user);

        return view('justificatifs-batch.period', [
            'module' => $module,
            'type' => $type,
            'typeConfig' => self::TYPES[$type],
            'gare' => $gare,
            'defaultStartDate' => now('Africa/Abidjan')->startOfMonth()->toDateString(),
            'defaultEndDate' => now('Africa/Abidjan')->toDateString(),
        ]);
    }

    public function download(Request $request, string $type, Gare $gare): BinaryFileResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->hasGlobalVisibility(), 403);
        abort_unless(array_key_exists($type, self::TYPES), 404);
        abort_unless($gare->is_active && ! $gare->is_virtual, 404);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ], [
            'start_date.required' => 'Veuillez renseigner la date de debut.',
            'end_date.required' => 'Veuillez renseigner la date de fin.',
            'end_date.after_or_equal' => 'La date de fin doit etre superieure ou egale a la date de debut.',
        ]);

        $startDate = Carbon::parse((string) $validated['start_date'])->startOfDay();
        $endDate = Carbon::parse((string) $validated['end_date'])->endOfDay();

        $pieces = $this->piecesForPeriod($type, $gare->id, $startDate, $endDate);

        $downloadDate = now('Africa/Abidjan')->format('Ymd_His');
        $gareSlug = Str::of($gare->name)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_')
            ->value();
        if ($gareSlug === '') {
            $gareSlug = 'gare';
        }

        $zipName = $gareSlug.'_'.$downloadDate.'.zip';
        $tempDir = storage_path('app/temp');
        File::ensureDirectoryExists($tempDir);
        $zipPath = $tempDir.DIRECTORY_SEPARATOR.$zipName;

        $zip = new ZipArchive();
        $openStatus = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        abort_unless($openStatus === true, 500, "Impossible de preparer l'archive ZIP.");

        $addedCount = 0;
        foreach ($pieces as $piece) {
            $resolved = $piece->resolveStorageLocation();
            if (! $resolved) {
                continue;
            }

            $sourcePath = Storage::disk($resolved['disk'])->path($resolved['path']);
            if (! is_file($sourcePath)) {
                continue;
            }

            $originalName = trim((string) ($piece->original_name ?: $piece->file_name ?: 'piece'));
            $baseName = trim((string) pathinfo($originalName, PATHINFO_FILENAME));
            $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
            if ($baseName === '') {
                $baseName = 'piece';
            }

            $safeBaseName = Str::of($baseName)
                ->ascii()
                ->replaceMatches('/[^A-Za-z0-9_-]+/', '_')
                ->trim('_')
                ->value();
            if ($safeBaseName === '') {
                $safeBaseName = 'piece';
            }

            $operationDate = $piece->attachable?->operation_date?->format('Y-m-d')
                ?: $piece->uploaded_at?->format('Y-m-d')
                ?: 'sans-date';

            $entryName = sprintf(
                '%03d_%s_%s%s',
                $addedCount + 1,
                $operationDate,
                $safeBaseName,
                $extension !== '' ? '.'.$extension : ''
            );

            if ($zip->addFile($sourcePath, $entryName)) {
                $addedCount++;
            }
        }

        $zip->close();

        if ($addedCount === 0) {
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }

            return back()->withErrors([
                'period' => 'Aucun justificatif disponible pour cette gare sur la periode selectionnee.',
            ])->withInput();
        }

        return response()
            ->download($zipPath, $zipName, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    protected function piecesForPeriod(string $type, int $gareId, Carbon $startDate, Carbon $endDate): Collection
    {
        $typeConfig = self::TYPES[$type];

        return PieceJustificative::query()
            ->where('document_type', $typeConfig['document_type'])
            ->whereHasMorph(
                'attachable',
                [$typeConfig['attachable']],
                function ($query) use ($gareId, $startDate, $endDate) {
                    $query->where('gare_id', $gareId)
                        ->whereDate('operation_date', '>=', $startDate->toDateString())
                        ->whereDate('operation_date', '<=', $endDate->toDateString());
                }
            )
            ->with('attachable')
            ->orderBy('uploaded_at')
            ->orderBy('id')
            ->get();
    }
}
