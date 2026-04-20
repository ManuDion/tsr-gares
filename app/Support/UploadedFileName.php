<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class UploadedFileName
{
    public static function build(?string $desiredName, UploadedFile $file, ?string $fallbackOriginalName = null): string
    {
        $originalName = $fallbackOriginalName ?: $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION) ?: 'pdf');

        $stem = self::sanitizeStem($desiredName);

        if ($stem === '') {
            $stem = self::sanitizeStem(pathinfo($originalName, PATHINFO_FILENAME));
        }

        if ($stem === '') {
            $stem = 'document';
        }

        return $stem.'.'.$extension;
    }

    public static function buildFromStoredName(?string $desiredName, string $fallbackOriginalName, ?string $mimeType = null): string
    {
        $extension = strtolower(pathinfo($fallbackOriginalName, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = self::guessExtensionFromMime($mimeType);
        }

        $stem = self::sanitizeStem($desiredName);
        if ($stem === '') {
            $stem = self::sanitizeStem(pathinfo($fallbackOriginalName, PATHINFO_FILENAME));
        }
        if ($stem === '') {
            $stem = 'document';
        }

        return $extension !== '' ? $stem.'.'.$extension : $stem;
    }

    protected static function sanitizeStem(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = pathinfo($value, PATHINFO_FILENAME);
        $value = preg_replace('/[\\\/\:\*\?\"\<\>\|]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        $value = trim($value, " .\t\n\r\0\x0B");

        return Str::limit($value, 120, '');
    }

    protected static function guessExtensionFromMime(?string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => 'pdf',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            default => '',
        };
    }
}
