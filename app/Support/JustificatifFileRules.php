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
        $max = $maxKb ?? (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120);

        return [
            $required ? 'required' : 'nullable',
            'file',
            self::typeRule(),
            'max:'.$max,
        ];
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
}

