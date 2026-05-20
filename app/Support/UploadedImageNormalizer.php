<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class UploadedImageNormalizer
{
    public static function normalizeImageToJpegBlob(UploadedFile $file, ?int $targetMaxKb = null): ?string
    {
        if (! self::isImageLike($file)) {
            return null;
        }

        $sourcePath = $file->getRealPath();
        if (! $sourcePath || ! is_file($sourcePath)) {
            return null;
        }

        $maxWidth = max(320, (int) env('JUSTIFICATIF_IMAGE_STANDARD_MAX_WIDTH', 1600));
        $maxHeight = max(320, (int) env('JUSTIFICATIF_IMAGE_STANDARD_MAX_HEIGHT', 1600));
        $initialQuality = min(92, max(60, (int) env('JUSTIFICATIF_IMAGE_STANDARD_QUALITY', 82)));
        $minQuality = min($initialQuality, max(45, (int) env('JUSTIFICATIF_IMAGE_STANDARD_MIN_QUALITY', 58)));
        $step = max(2, (int) env('JUSTIFICATIF_IMAGE_STANDARD_QUALITY_STEP', 6));
        $maxKb = $targetMaxKb
            ?: (int) env('JUSTIFICATIF_IMAGE_STANDARD_MAX_KB', min((int) env('JUSTIFICATIF_MAX_SIZE_KB', 10240), 1536));
        $maxBytes = max(128 * 1024, $maxKb * 1024);

        $imagickBlob = self::normalizeWithImagick($sourcePath, $maxWidth, $maxHeight, $initialQuality, $minQuality, $step, $maxBytes);
        if ($imagickBlob !== null) {
            return $imagickBlob;
        }

        return self::normalizeWithGd($sourcePath, $maxWidth, $maxHeight, $initialQuality, $minQuality, $step, $maxBytes);
    }

    public static function convertHeicToJpegBlob(UploadedFile $file): ?string
    {
        // Backward-compatible name kept for existing controller calls.
        return self::normalizeImageToJpegBlob($file);
    }

    protected static function normalizeWithImagick(
        string $sourcePath,
        int $maxWidth,
        int $maxHeight,
        int $initialQuality,
        int $minQuality,
        int $step,
        int $maxBytes
    ): ?string {
        if (! class_exists(\Imagick::class)) {
            return null;
        }

        $bestBlob = null;
        try {
            $imagick = new \Imagick();
            $imagick->readImage($sourcePath);
            if ($imagick->getNumberImages() > 1) {
                $imagick->setIteratorIndex(0);
            }
            $imagick->autoOrient();
            $imagick->setImageBackgroundColor('white');
            $flattened = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $flattened->stripImage();
            self::resizeImagick($flattened, $maxWidth, $maxHeight);
            $flattened->setImageFormat('jpeg');
            $flattened->setImageCompression(\Imagick::COMPRESSION_JPEG);

            for ($quality = $initialQuality; $quality >= $minQuality; $quality -= $step) {
                $flattened->setImageCompressionQuality($quality);
                $blob = $flattened->getImageBlob();
                if (! is_string($blob) || $blob === '') {
                    continue;
                }

                $bestBlob = $blob;
                if (strlen($blob) <= $maxBytes) {
                    break;
                }
            }

            $flattened->clear();
            $flattened->destroy();
            $imagick->clear();
            $imagick->destroy();

            return $bestBlob;
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function normalizeWithGd(
        string $sourcePath,
        int $maxWidth,
        int $maxHeight,
        int $initialQuality,
        int $minQuality,
        int $step,
        int $maxBytes
    ): ?string {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $raw = @file_get_contents($sourcePath);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $source = @imagecreatefromstring($raw);
        if (! $source) {
            return null;
        }

        $sourceWidth = (int) imagesx($source);
        $sourceHeight = (int) imagesy($source);
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);

            return null;
        }

        [$targetWidth, $targetHeight] = self::scaledDimensions($sourceWidth, $sourceHeight, $maxWidth, $maxHeight);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if (! $canvas) {
            imagedestroy($source);

            return null;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        $bestBlob = null;
        for ($quality = $initialQuality; $quality >= $minQuality; $quality -= $step) {
            ob_start();
            imagejpeg($canvas, null, $quality);
            $blob = ob_get_clean();

            if (! is_string($blob) || $blob === '') {
                continue;
            }

            $bestBlob = $blob;
            if (strlen($blob) <= $maxBytes) {
                break;
            }
        }

        imagedestroy($canvas);
        imagedestroy($source);

        return $bestBlob;
    }

    protected static function resizeImagick(\Imagick $image, int $maxWidth, int $maxHeight): void
    {
        $width = (int) $image->getImageWidth();
        $height = (int) $image->getImageHeight();
        if ($width <= 0 || $height <= 0) {
            return;
        }

        [$targetWidth, $targetHeight] = self::scaledDimensions($width, $height, $maxWidth, $maxHeight);
        if ($targetWidth === $width && $targetHeight === $height) {
            return;
        }

        $image->resizeImage($targetWidth, $targetHeight, \Imagick::FILTER_LANCZOS, 1, true);
    }

    /**
     * @return array{0:int,1:int}
     */
    protected static function scaledDimensions(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $ratio = min(
            1,
            $maxWidth / max(1, $width),
            $maxHeight / max(1, $height)
        );

        return [
            max(1, (int) floor($width * $ratio)),
            max(1, (int) floor($height * $ratio)),
        ];
    }

    protected static function isImageLike(UploadedFile $file): bool
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'], true)) {
            return true;
        }

        $mime = strtolower((string) ($file->getClientMimeType() ?: $file->getMimeType() ?: ''));

        return str_starts_with($mime, 'image/');
    }

    protected static function isHeicLike(UploadedFile $file): bool
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($ext, ['heic', 'heif'], true)) {
            return true;
        }

        $mime = strtolower((string) ($file->getClientMimeType() ?: $file->getMimeType() ?: ''));

        return in_array($mime, [
            'image/heic',
            'image/heif',
            'application/heic',
            'application/heif',
        ], true);
    }
}
