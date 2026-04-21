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

    public static function defaultLabel(string $moduleLabel, ?string $gareLabel, ?string $operationDate, ?string $custom = null): string
    {
        $candidate = trim((string) $custom);
        if ($candidate !== '') {
            return $candidate;
        }

        $parts = [
            self::sanitizeStem($moduleLabel),
            self::sanitizeStem($gareLabel ?: 'Sans gare'),
            self::sanitizeStem($operationDate ?: now()->format('Y-m-d')),
        ];

        return implode('_', array_filter($parts)) ?: 'document';
    }

    protected static function sanitizeStem(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = pathinfo($value, PATHINFO_FILENAME);
        $value = preg_replace('~[\\/:*?"<>|]+~u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        $value = trim($value, " .\t\n\r\0\x0B");
        $value = Str::ascii($value);
        $value = preg_replace('/\s+/u', '_', $value) ?? '';

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
