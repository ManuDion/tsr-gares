<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PieceJustificative extends Model
{
    use HasFactory;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'document_type',
        'original_name',
        'file_name',
        'mime_type',
        'size',
        'disk',
        'path',
        'uploaded_by',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(DocumentAnalysis::class)->latestOfMany();
    }

    public function exists(): bool
    {
        return $this->resolveStorageLocation() !== null;
    }

    /**
     * Expose computed disk/path candidates for production diagnostics.
     *
     * @return array{disks:array<int,string>,paths:array<int,string>}
     */
    public function storageCandidates(): array
    {
        return [
            'disks' => $this->candidateDisks(),
            'paths' => $this->candidatePaths(),
        ];
    }

    /**
     * Resolve legacy/current storage location for this justificatif.
     *
     * @return array{disk:string,path:string}|null
     */
    public function resolveStorageLocation(): ?array
    {
        foreach ($this->candidateDisks() as $disk) {
            if (! array_key_exists($disk, config('filesystems.disks', []))) {
                continue;
            }

            foreach ($this->candidatePaths() as $path) {
                if ($path === '') {
                    continue;
                }

                if (Storage::disk($disk)->exists($path)) {
                    return ['disk' => $disk, 'path' => $path];
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function candidateDisks(): array
    {
        $preferredDisk = trim((string) ($this->disk ?? ''));
        $envDisk = trim((string) env('JUSTIFICATIF_PRIVATE_DISK', 'private'));

        return collect([$preferredDisk, $envDisk, 'private', 'local', 'public'])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function candidatePaths(): array
    {
        $rawPath = str_replace('\\', '/', trim((string) ($this->path ?? '')));
        $fileName = str_replace('\\', '/', trim((string) ($this->file_name ?? '')));

        $candidates = [];
        if ($rawPath !== '') {
            $normalizedRawPath = ltrim($rawPath, '/');
            $candidates[] = $normalizedRawPath;

            $appStoragePath = str_replace('\\', '/', storage_path('app'));
            $prefixes = [
                $appStoragePath.'/',
                $appStoragePath.'/private/',
                $appStoragePath.'/public/',
                'storage/app/private/',
                'storage/app/public/',
                'storage/app/',
                'app/private/',
                'app/public/',
                'private/',
                'public/',
                'storage/',
            ];

            foreach ($prefixes as $prefix) {
                $stripped = preg_replace(
                    '/^'.preg_quote($prefix, '/').'/i',
                    '',
                    $normalizedRawPath,
                    1
                );

                if (is_string($stripped) && $stripped !== $normalizedRawPath) {
                    $candidates[] = ltrim($stripped, '/');
                }
            }
        }

        if ($fileName !== '') {
            $candidates[] = basename($fileName);
            $candidates[] = 'justificatifs/'.basename($fileName);
            $folder = match ((string) $this->document_type) {
                'recette' => 'recettes',
                'depense' => 'depenses',
                'versement_bancaire' => 'versements',
                default => null,
            };

            if ($folder) {
                $candidates[] = "justificatifs/{$folder}/".basename($fileName);
            }
        }

        return collect($candidates)
            ->map(fn (string $path) => trim(str_replace('\\', '/', $path)))
            ->filter(fn (string $path) => $path !== '')
            ->unique()
            ->values()
            ->all();
    }
}
