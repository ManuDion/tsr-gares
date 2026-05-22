<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class JustificatifFileRules
{
    /**
     * @return array<int, mixed>
     */
    public static function single(bool $required = true, ?int $maxKb = null): array
    {
        $max = $maxKb;
        if ($max === null) {
            $configuredMax = (int) env('JUSTIFICATIF_MAX_SIZE_KB', 10240);
            $max = $configuredMax > 0 ? $configuredMax : null;
        }

        $rules = [
            $required ? 'required' : 'nullable',
            'file',
            self::typeRule(),
        ];

        if (is_int($max) && $max > 0) {
            $rules[] = self::sizeRule($max);
        }

        return $rules;
    }

    /**
     * @return \Closure(string, mixed, \Closure): void
     */
    public static function typeRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! $value instanceof UploadedFile) {
                $fail('Le fichier selectionne est invalide.');
                return;
            }

            $extension = strtolower((string) $value->getClientOriginalExtension());
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];

            if (! in_array($extension, $allowedExtensions, true)) {
                $fail('Format non pris en charge. Utilisez PDF, JPG, PNG, WEBP ou HEIC/HEIF.');
            }
        };
    }

    /**
     * @return \Closure(string, mixed, \Closure): void
     */
    public static function sizeRule(int $maxKb): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($maxKb): void {
            if (! $value instanceof UploadedFile) {
                return;
            }

            $size = (int) ($value->getSize() ?? 0);
            if ($size <= 0) {
                return;
            }

            if ($size > ($maxKb * 1024)) {
                $maxLabel = self::formatSizeLabel($maxKb);
                $fileName = trim((string) $value->getClientOriginalName());
                $fileLabel = $fileName !== '' ? '"'.$fileName.'"' : 'selectionne';
                $fail("Le fichier {$fileLabel} depasse {$maxLabel}. Taille maximale autorisee: {$maxLabel}.");
            }
        };
    }

    protected static function formatSizeLabel(int $maxKb): string
    {
        if ($maxKb > 0 && $maxKb % 1024 === 0) {
            return ((int) ($maxKb / 1024)).' Mo';
        }

        return $maxKb.' Ko';
    }
}
