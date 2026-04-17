<?php

namespace App\Services;

use App\Models\DocumentAnalysis;
use App\Models\PieceJustificative;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class DocumentAnalysisService
{
    public function analyze(PieceJustificative $piece): ?DocumentAnalysis
    {
        if (! Config::boolean('services.ocr.enabled')) {
            return null;
        }

        $analysis = $this->analyzeFile(
            Storage::disk($piece->disk)->path($piece->path),
            $piece->original_name,
            $piece->mime_type,
            $piece->attachable?->gare ? collect([$piece->attachable->gare]) : collect(),
            $piece->attachable->gare_id ?? null,
        );

        return $this->persistAnalysis($piece, $analysis);
    }

public function analyzeFile(
    string $absolutePath,
    string $originalName,
    ?string $mimeType = null,
    Collection|array $availableGares = [],
    ?int $preferredGareId = null
): array {
    if (! Config::boolean('services.ocr.enabled')) {
        throw new RuntimeException("L'OCR local n'est pas activé dans le fichier .env.");
    }

    $gares = $availableGares instanceof Collection ? $availableGares->values() : collect($availableGares)->values();
    $result = $this->extractRawText($absolutePath, $mimeType);
    $originalLines = collect(preg_split('/\r\n|\r|\n/', $result['text']) ?: [])
        ->map(fn ($line) => trim($line))
        ->filter()
        ->values();
    $normalizedLines = $originalLines
        ->map(fn ($line) => $this->normalizeForSearch($line))
        ->filter()
        ->values();

    $parsed = $this->parseVersementData(
        $result['text'],
        $originalLines,
        $normalizedLines,
        $gares,
        $preferredGareId,
        $originalName
    );

    return [
        'status' => 'completed',
        'provider' => Config::get('services.ocr.driver', 'local_tesseract'),
        'extracted_data' => $parsed['fields'],
        'confidence' => $parsed['confidence'],
        'raw_payload' => [
            'source_name' => $originalName,
            'source_mime' => $mimeType,
            'raw_text' => $result['text'],
            'diagnostics' => $result['diagnostics'],
            'preview_lines' => $this->previewLines($result['text']),
            'detected_template' => $parsed['detected_template'] ?? 'Générique',
            'field_snippets' => $parsed['field_snippets'] ?? [],
        ],
    ];
}

    public function persistAnalysis(PieceJustificative $piece, array $analysis): DocumentAnalysis
    {
        return DocumentAnalysis::create([
            'piece_justificative_id' => $piece->id,
            'status' => $analysis['status'] ?? 'completed',
            'provider' => $analysis['provider'] ?? Config::get('services.ocr.driver', 'local_tesseract'),
            'extracted_data' => $analysis['extracted_data'] ?? [],
            'confidence' => $analysis['confidence'] ?? [],
            'raw_payload' => $analysis['raw_payload'] ?? [],
        ]);
    }

    protected function extractRawText(string $absolutePath, ?string $mimeType = null): array
    {
        $workingDirectory = $this->makeWorkingDirectory();
        $extension = Str::lower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $isPdf = $mimeType === 'application/pdf' || $extension === 'pdf';

        $diagnostics = [];

        if ($isPdf) {
            $directText = $this->extractTextFromPdf($absolutePath, $workingDirectory);

            if ($this->isUsableExtractedText($directText)) {
                $diagnostics[] = 'Texte PDF extrait directement.';
                return [
                    'text' => trim($directText),
                    'diagnostics' => $diagnostics,
                ];
            }

            $diagnostics[] = 'Texte PDF direct indisponible ou insuffisant, bascule vers OCR.';
            $imagePath = $this->convertPdfToImage($absolutePath, $workingDirectory);
            $diagnostics[] = 'PDF converti en image pour OCR.';
        } else {
            $imagePath = $this->prepareImageForOcr($absolutePath, $workingDirectory);
            $diagnostics[] = 'Image préparée pour OCR.';
        }

        $text = trim($this->runTesseract($imagePath));
        $diagnostics[] = 'Texte OCR extrait via Tesseract.';

        if ($text === '') {
            throw new RuntimeException("Aucun texte n'a été détecté sur le bordereau. Vérifiez la netteté du document.");
        }

        return [
            'text' => $text,
            'diagnostics' => $diagnostics,
        ];
    }

    protected function extractTextFromPdf(string $pdfPath, string $workingDirectory): string
    {
        $txtPath = $workingDirectory.'/source.txt';

        $binaries = $this->resolveBinaryCandidates([
            Config::get('services.ocr.pdf_text_binary', 'pdftotext'),
        ], [
            'C:/Program Files/poppler/bin/pdftotext.exe',
            'C:/Program Files/poppler/Library/bin/pdftotext.exe',
            'C:/poppler/bin/pdftotext.exe',
            'C:/poppler/Library/bin/pdftotext.exe',
        ]);

        foreach ($binaries as $binary) {
            try {
                $this->runProcess([$binary, '-layout', $pdfPath, $txtPath]);

                if (File::exists($txtPath)) {
                    return (string) File::get($txtPath);
                }
            } catch (\Throwable $exception) {
                // Fallback to OCR conversion.
            }
        }

        return '';
    }

    protected function isUsableExtractedText(string $text): bool
    {
        $compact = preg_replace('/\s+/', ' ', trim($text));

        return is_string($compact) && mb_strlen($compact) >= 40;
    }

    protected function convertPdfToImage(string $pdfPath, string $workingDirectory): string
    {
        $outputPrefix = $workingDirectory.'/page-1';
        $pngPath = $outputPrefix.'.png';
        $errors = [];

        if ($this->convertPdfUsingImagickExtension($pdfPath, $pngPath)) {
            return $pngPath;
        }

        $pdftoppmBinaries = $this->resolveBinaryCandidates([
            Config::get('services.ocr.pdf_to_image_binary', 'pdftoppm'),
        ], [
            'C:/Program Files/poppler/bin/pdftoppm.exe',
            'C:/Program Files/poppler/Library/bin/pdftoppm.exe',
            'C:/poppler/bin/pdftoppm.exe',
            'C:/poppler/Library/bin/pdftoppm.exe',
        ]);

        $pdftocairoBinaries = $this->resolveBinaryCandidates([
            Config::get('services.ocr.pdf_to_image_cairo_binary', 'pdftocairo'),
        ], [
            'C:/Program Files/poppler/bin/pdftocairo.exe',
            'C:/Program Files/poppler/Library/bin/pdftocairo.exe',
            'C:/poppler/bin/pdftocairo.exe',
            'C:/poppler/Library/bin/pdftocairo.exe',
        ]);

        $mutoolBinaries = $this->resolveBinaryCandidates([
            Config::get('services.ocr.mutool_binary', 'mutool'),
        ], [
            'C:/Program Files/MuPDF/mutool.exe',
            'C:/MuPDF/mutool.exe',
        ]);

        $imageMagickBinaries = $this->resolveBinaryCandidates([
            Config::get('services.ocr.imagemagick_binary', 'magick'),
        ], [
            'C:/Program Files/ImageMagick-*/magick.exe',
            'C:/ImageMagick*/magick.exe',
        ]);

        $ghostscriptBinaries = $this->resolveBinaryCandidates([
            Config::get('services.ocr.ghostscript_binary', 'gswin64c'),
        ], [
            'C:/Program Files/gs/gs*/bin/gswin64c.exe',
            'C:/Program Files/Ghostscript/*/bin/gswin64c.exe',
        ]);

        $attempts = [];

        foreach ($pdftoppmBinaries as $binary) {
            $attempts[] = [
                'label' => 'pdftoppm',
                'command' => [$binary, '-png', '-r', '300', '-f', '1', '-singlefile', $pdfPath, $outputPrefix],
            ];
        }

        foreach ($pdftocairoBinaries as $binary) {
            $attempts[] = [
                'label' => 'pdftocairo',
                'command' => [$binary, '-png', '-r', '300', '-f', '1', '-l', '1', '-singlefile', $pdfPath, $outputPrefix],
            ];
        }

        foreach ($mutoolBinaries as $binary) {
            $attempts[] = [
                'label' => 'mutool',
                'command' => [$binary, 'draw', '-r', '300', '-o', $pngPath, $pdfPath, '1'],
            ];
        }

        foreach ($imageMagickBinaries as $binary) {
            $attempts[] = [
                'label' => 'imagemagick',
                'command' => [$binary, '-density', '300', $pdfPath.'[0]', '-alpha', 'remove', '-colorspace', 'Gray', '-strip', $pngPath],
            ];
        }

        foreach ($ghostscriptBinaries as $binary) {
            $attempts[] = [
                'label' => 'ghostscript',
                'command' => [$binary, '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=png16m', '-r300', '-dFirstPage=1', '-dLastPage=1', '-o', $pngPath, $pdfPath],
            ];
        }

        foreach ($attempts as $attempt) {
            try {
                $this->runProcess($attempt['command']);

                if (File::exists($pngPath)) {
                    return $pngPath;
                }
            } catch (\Throwable $exception) {
                $errors[] = $attempt['label'].': '.$exception->getMessage();
            }
        }

        throw new RuntimeException(
            "La conversion PDF a échoué. Installez Poppler (pdftoppm/pdftocairo) ou configurez le chemin complet d'un convertisseur PDF dans le fichier .env. Détails : ".implode(' | ', $errors)
        );
    }

    protected function convertPdfUsingImagickExtension(string $pdfPath, string $pngPath): bool
    {
        if (! class_exists(\Imagick::class)) {
            return false;
        }

        try {
            $imagick = new \Imagick();
            $imagick->setResolution(300, 300);
            $imagick->readImage($pdfPath.'[0]');
            $imagick->setImageBackgroundColor('white');
            $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $imagick->setImageColorspace(\Imagick::COLORSPACE_GRAY);
            $imagick->setImageFormat('png');
            $imagick->writeImage($pngPath);
            $imagick->clear();
            $imagick->destroy();

            return File::exists($pngPath);
        } catch (\Throwable $exception) {
            return false;
        }
    }

    protected function prepareImageForOcr(string $imagePath, string $workingDirectory): string
    {
        $extension = pathinfo($imagePath, PATHINFO_EXTENSION) ?: 'png';
        $preparedPath = $workingDirectory.'/prepared.'.$extension;
        $imageMagickBinaries = $this->resolveBinaryCandidates([
            Config::get('services.ocr.imagemagick_binary', 'magick'),
        ], [
            'C:/Program Files/ImageMagick-*/magick.exe',
            'C:/ImageMagick*/magick.exe',
        ]);

        if ($this->prepareImageUsingImagickExtension($imagePath, $preparedPath)) {
            return $preparedPath;
        }

        foreach ($imageMagickBinaries as $imageMagickBinary) {
            try {
                $this->runProcess([
                    $imageMagickBinary,
                    $imagePath,
                    '-auto-orient',
                    '-colorspace', 'Gray',
                    '-strip',
                    '-resize', '2200x2200>',
                    $preparedPath,
                ]);

                if (File::exists($preparedPath)) {
                    return $preparedPath;
                }
            } catch (\Throwable $exception) {
                // Fallback to the original image.
            }
        }

        return $imagePath;
    }

    protected function prepareImageUsingImagickExtension(string $imagePath, string $preparedPath): bool
    {
        if (! class_exists(\Imagick::class)) {
            return false;
        }

        try {
            $imagick = new \Imagick($imagePath);
            $imagick->autoOrient();
            $imagick->setImageColorspace(\Imagick::COLORSPACE_GRAY);
            $imagick->stripImage();
            $imagick->writeImage($preparedPath);
            $imagick->clear();
            $imagick->destroy();

            return File::exists($preparedPath);
        } catch (\Throwable $exception) {
            return false;
        }
    }

    protected function runTesseract(string $imagePath): string
    {
        $languages = Config::get('services.ocr.ocr_languages', 'fra+eng');
        $binaries = $this->resolveBinaryCandidates([
            Config::get('services.ocr.tesseract_binary', 'tesseract'),
        ], [
            'C:/Program Files/Tesseract-OCR/tesseract.exe',
            'C:/Tesseract-OCR/tesseract.exe',
        ]);

        $lastError = null;

        foreach ($binaries as $binary) {
            try {
                return $this->runProcess([
                    $binary,
                    $imagePath,
                    'stdout',
                    '-l', $languages,
                    '--psm', '6',
                ]);
            } catch (\Throwable $exception) {
                $lastError = $exception;

                if (Str::contains($languages, '+')) {
                    try {
                        return $this->runProcess([
                            $binary,
                            $imagePath,
                            'stdout',
                            '-l', 'eng',
                            '--psm', '6',
                        ]);
                    } catch (\Throwable $fallbackException) {
                        $lastError = $fallbackException;
                    }
                }
            }
        }

        throw new RuntimeException("Tesseract n'a pas pu lire le document : ".($lastError?->getMessage() ?: 'binaire introuvable.'));
    }

    protected function parseVersementData(
    string $rawText,
    Collection $originalLines,
    Collection $normalizedLines,
    Collection $availableGares,
    ?int $preferredGareId,
    string $originalName
): array {
    $normalized = $this->normalizeForSearch($rawText);
    $template = $this->detectBankTemplate($normalized, $normalizedLines, $originalName);

    $parsed = match ($template) {
        'coris_bank' => $this->parseCorisBankData($normalized, $normalizedLines, $availableGares, $preferredGareId, $originalName),
        'ecobank' => $this->parseEcobankData($normalized, $normalizedLines, $availableGares, $preferredGareId, $originalName),
        default => $this->parseGenericVersementData($normalized, $normalizedLines, $availableGares, $preferredGareId, $originalName),
    };

    $parsed['field_snippets'] = $this->buildFieldSnippets($template, $originalLines, $normalizedLines);

    return $parsed;
}

    protected function detectBankTemplate(string $normalized, Collection $lines, string $originalName): string
    {
        $filename = $this->normalizeForSearch(pathinfo($originalName, PATHINFO_FILENAME));

        if (Str::contains($normalized, 'CORIS BANK') || Str::contains($normalized, 'BORDEREAU DE VERSEMENT ESPECES')) {
            return 'coris_bank';
        }

        if (Str::contains($normalized, 'ECOBANK') || Str::contains($filename, 'ECOBANK')) {
            return 'ecobank';
        }

        if ($lines->contains(fn ($line) => Str::contains($line, 'MONTANT VERSE') || Str::contains($line, 'MONTANT CREDITE'))) {
            return 'ecobank';
        }

        return 'generic';
    }

    protected function parseCorisBankData(string $normalized, Collection $lines, Collection $availableGares, ?int $preferredGareId, string $originalName): array
    {
        $operationDate = $this->extractDateByLabels($lines, ['DATE', 'LE'])
            ?? $this->extractNamedMonthDate($normalized)
            ?? $this->extractAnyDate($normalized)
            ?? now()->toDateString();

        $amount = $this->extractAmountByLabels($lines, ['MONTANT NET', 'MONTANT'])
            ?? $this->extractAmount($lines, $normalized);

        $reference = $this->extractAgencyName($lines)
            ?? $this->extractReferenceByLabels($lines, ['AGENCE'])
            ?? pathinfo($originalName, PATHINFO_FILENAME);

        $gare = $this->extractGare(
            implode(' ', $this->extractContextLines($lines, ['ADRESSE', 'MOTIF', 'AGENCE'])->all()).' '.$normalized,
            $availableGares,
            $preferredGareId
        );

        return [
            'detected_template' => 'Coris Bank',
            'fields' => [
                'gare_id' => $gare['gare_id'] ?? $preferredGareId,
                'gare_label' => $gare['gare_label'] ?? null,
                'operation_date' => $operationDate,
                'receipt_date' => null,
                'amount' => $amount,
                'bank_name' => 'Coris Bank',
                'reference' => $reference,
            ],
            'confidence' => [
                'gare_id' => $gare['confidence'] ?? ($preferredGareId ? 1.0 : 0.0),
                'operation_date' => $operationDate ? 0.92 : 0.25,
                'amount' => $amount ? 0.94 : 0.0,
                'bank_name' => 0.98,
                'reference' => $reference ? 0.91 : 0.0,
            ],
        ];
    }

    protected function parseEcobankData(string $normalized, Collection $lines, Collection $availableGares, ?int $preferredGareId, string $originalName): array
    {
        $operationDate = $this->extractDateByLabels($lines, ['DATE'])
            ?? $this->extractNamedMonthDate($normalized)
            ?? $this->extractAnyDate($normalized)
            ?? now()->toDateString();

        $amount = $this->extractAmountByLabels($lines, ['MONTANT VERSE', 'MONTANT CREDITE', 'MONTANT'])
            ?? $this->extractAmount($lines, $normalized);

        $reference = $this->extractAgencyName($lines)
            ?? $this->extractReferenceByLabels($lines, ['AGENCE'])
            ?? pathinfo($originalName, PATHINFO_FILENAME);

        $gare = $this->extractGare(
            implode(' ', $this->extractContextLines($lines, ['AGENCE', 'MOTIF', 'REMARQUES'])->all()).' '.$normalized,
            $availableGares,
            $preferredGareId
        );

        return [
            'detected_template' => 'Ecobank',
            'fields' => [
                'gare_id' => $gare['gare_id'] ?? $preferredGareId,
                'gare_label' => $gare['gare_label'] ?? null,
                'operation_date' => $operationDate,
                'receipt_date' => null,
                'amount' => $amount,
                'bank_name' => 'Ecobank',
                'reference' => $reference,
            ],
            'confidence' => [
                'gare_id' => $gare['confidence'] ?? ($preferredGareId ? 1.0 : 0.0),
                'operation_date' => $operationDate ? 0.94 : 0.25,
                'amount' => $amount ? 0.95 : 0.0,
                'bank_name' => 0.98,
                'reference' => $reference ? 0.95 : 0.0,
            ],
        ];
    }

    protected function parseGenericVersementData(string $normalized, Collection $lines, Collection $availableGares, ?int $preferredGareId, string $originalName): array
    {
        $operationDate = $this->extractDateByLabels($lines, ['DATE'])
            ?? $this->extractNamedMonthDate($normalized)
            ?? $this->extractAnyDate($normalized)
            ?? now()->toDateString();

        $amount = $this->extractAmount($lines, $normalized);
        $bank = $this->extractBank($lines);
        $reference = $this->extractAgencyName($lines) ?: ($this->extractReference($lines) ?: pathinfo($originalName, PATHINFO_FILENAME));
        $matchedGare = $this->extractGare($normalized, $availableGares, $preferredGareId);

        return [
            'detected_template' => 'Générique',
            'fields' => [
                'gare_id' => $matchedGare['gare_id'] ?? $preferredGareId,
                'gare_label' => $matchedGare['gare_label'] ?? null,
                'operation_date' => $operationDate,
                'receipt_date' => null,
                'amount' => $amount,
                'bank_name' => $bank,
                'reference' => $reference,
            ],
            'confidence' => [
                'gare_id' => $matchedGare['confidence'] ?? ($preferredGareId ? 1.0 : 0.0),
                'operation_date' => $operationDate ? 0.80 : 0.25,
                'amount' => $amount ? 0.88 : 0.0,
                'bank_name' => $bank ? 0.74 : 0.0,
                'reference' => $reference ? 0.72 : 0.0,
            ],
        ];
    }

    protected function buildFieldSnippets(string $template, Collection $originalLines, Collection $normalizedLines): array
{
    $mapping = [
        'coris_bank' => [
            'operation_date' => ['LE', 'DATE'],
            'amount' => ['MONTANT', 'MONTANT NET', 'MONTANT RECU'],
            'bank_name' => ['BANK', 'BORDEREAU DE VERSEMENT ESPECES'],
            'reference' => ['AGENCE'],
            'gare_id' => ['ADRESSE', 'MOTIF', 'AGENCE'],
        ],
        'ecobank' => [
            'operation_date' => ['DATE'],
            'amount' => ['MONTANT VERSE', 'MONTANT CREDITE', 'MONTANT'],
            'bank_name' => ['ECOBANK', 'BANQUE'],
            'reference' => ['AGENCE'],
            'gare_id' => ['AGENCE', 'MOTIF', 'REMARQUES'],
        ],
        'generic' => [
            'operation_date' => ['DATE'],
            'amount' => ['MONTANT', 'TOTAL', 'VERSEMENT'],
            'bank_name' => ['BANQUE', 'BANK'],
            'reference' => ['AGENCE', 'REFERENCE', 'REF'],
            'gare_id' => ['AGENCE', 'MOTIF', 'REMARQUES', 'ADRESSE'],
        ],
    ];

    $labels = $mapping[$template] ?? $mapping['generic'];
    $snippets = [];

    foreach ($labels as $field => $needles) {
        $snippets[$field] = $this->findOriginalSnippet($originalLines, $normalizedLines, $needles)
            ?? ($field === 'reference' ? $this->extractAgencyName($normalizedLines) : null);
    }

    return array_filter($snippets, fn ($value) => filled($value));
}

    protected function findOriginalSnippet(Collection $originalLines, Collection $normalizedLines, array $needles): ?string
{
    foreach ($normalizedLines as $index => $line) {
        foreach ($needles as $needle) {
            if (! Str::contains($line, $needle)) {
                continue;
            }

            $slice = $originalLines->slice(max(0, $index), 2)->values()->all();
            $snippet = trim(implode(PHP_EOL, array_filter($slice)));

            if ($snippet !== '') {
                return $snippet;
            }
        }
    }

    return null;
}

    protected function extractAgencyName(Collection $lines): ?string
{
    foreach ($lines as $index => $line) {
        if (! Str::contains($line, 'AGENCE')) {
            continue;
        }

        if ($inline = $this->extractInlineLabelValue($line, ['AGENCE'])) {
            return $inline;
        }

        foreach ([$index + 1, $index + 2] as $nextIndex) {
            $nextLine = trim((string) $lines->get($nextIndex, ''));

            if ($this->looksLikeAgencyName($nextLine)) {
                return $this->cleanupExtractedText($nextLine);
            }
        }
    }

    return null;
}

    protected function extractInlineLabelValue(string $line, array $labels): ?string
{
    foreach ($labels as $label) {
        if (! Str::contains($line, $label)) {
            continue;
        }

        $cleaned = preg_replace('/^.*?'.$label.'[^A-Z0-9]*?/u', '', $line);
        $cleaned = $this->cleanupExtractedText((string) $cleaned);

        if ($this->looksLikeAgencyName($cleaned)) {
            return $cleaned;
        }
    }

    return null;
}

    protected function looksLikeAgencyName(?string $value): bool
{
    if (! $value) {
        return false;
    }

    $value = $this->cleanupExtractedText($value);

    if ($value === '' || Str::contains($value, ['DATE', 'MONTANT', 'REFERENCE', 'REF', 'BANK'])) {
        return false;
    }

    return (bool) preg_match('/[A-Z]{3,}/u', $value);
}

    protected function cleanupExtractedText(string $value): string
{
    $value = preg_replace('/[_:;]+/u', ' ', $value);
    $value = preg_replace('/\s+/u', ' ', (string) $value);

    return trim((string) $value);
}

    protected function extractDateByLabels(Collection $lines, array $labels): ?string
    {
        foreach ($labels as $label) {
            foreach ($lines as $line) {
                if (! Str::contains($line, $label)) {
                    continue;
                }

                if (preg_match('/(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4})/', $line, $matches)) {
                    return $this->toIsoDate($matches[1]);
                }

                if ($date = $this->extractNamedMonthDate($line)) {
                    return $date;
                }
            }
        }

        return null;
    }

    protected function extractNamedMonthDate(string $value): ?string
    {
        $value = $this->normalizeForSearch($value);

        if (! preg_match('/(\d{1,2})[\/\-\s]+([A-Z]{3,12})[\/\-\s]+(\d{2,4})/', $value, $matches)) {
            return null;
        }

        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $monthName = $matches[2];
        $year = (int) $matches[3];

        if ($year < 100) {
            $year += $year >= 70 ? 1900 : 2000;
        }

        $months = [
            'JAN' => 1, 'JANV' => 1, 'JANVIER' => 1, 'JANUARY' => 1,
            'FEV' => 2, 'FEVR' => 2, 'FEVRIER' => 2, 'FEB' => 2, 'FEBRUARY' => 2,
            'MAR' => 3, 'MARS' => 3, 'MARCH' => 3,
            'AVR' => 4, 'AVRIL' => 4, 'APR' => 4, 'APRIL' => 4,
            'MAI' => 5, 'MAY' => 5,
            'JUN' => 6, 'JUIN' => 6, 'JUNE' => 6,
            'JUL' => 7, 'JUIL' => 7, 'JUILLET' => 7, 'JULY' => 7,
            'AOU' => 8, 'AOUT' => 8, 'AUG' => 8, 'AUGUST' => 8,
            'SEP' => 9, 'SEPT' => 9, 'SEPTEMBRE' => 9, 'SEPTEMBER' => 9,
            'OCT' => 10, 'OCTOBRE' => 10, 'OCTOBER' => 10,
            'NOV' => 11, 'NOVEMBRE' => 11, 'NOVEMBER' => 11,
            'DEC' => 12, 'DECEMBRE' => 12, 'DECEMBER' => 12,
        ];

        $month = $months[$monthName] ?? null;

        if (! $month) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, (int) $day);
    }

    protected function extractAnyDate(string $normalized): ?string
    {
        if (preg_match('/(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4})/', $normalized, $matches)) {
            return $this->toIsoDate($matches[1]);
        }

        return $this->extractNamedMonthDate($normalized);
    }

    protected function extractAmountByLabels(Collection $lines, array $labels): ?string
    {
        foreach ($labels as $label) {
            foreach ($lines as $line) {
                if (! Str::contains($line, $label)) {
                    continue;
                }

                if (preg_match('/((?:\d{1,3}(?:[ .,\x{00A0}]\d{3})+|\d+)(?:[,.]\d{2})?)/u', $line, $matches)) {
                    $normalized = $this->normalizeAmount($matches[1]);

                    if ($this->isPlausibleAmount($normalized)) {
                        return $normalized;
                    }
                }
            }
        }

        return null;
    }

    protected function extractAmount(Collection $lines, string $normalized): ?string
    {
        $keywordPatterns = [
            '/(?:MONTANT NET|MONTANT VERSE|MONTANT CREDITE|MONTANT|SOMME|TOTAL|VERSEMENT)[^\d]{0,25}((?:\d{1,3}(?:[ .,\x{00A0}]\d{3})+|\d+)(?:[,.]\d{2})?)/u',
            '/((?:\d{1,3}(?:[ .,\x{00A0}]\d{3})+|\d+)(?:[,.]\d{2})?)\s*(?:FCFA|XOF)/u',
        ];

        foreach ($lines as $line) {
            foreach ($keywordPatterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    $amount = $this->normalizeAmount($matches[1]);

                    if ($this->isPlausibleAmount($amount)) {
                        return $amount;
                    }
                }
            }
        }

        preg_match_all('/((?:\d{1,3}(?:[ .,\x{00A0}]\d{3})+|\d{4,})(?:[,.]\d{2})?)/u', $normalized, $matches);

        $candidates = collect($matches[1] ?? [])
            ->map(fn ($value) => $this->normalizeAmount($value))
            ->filter(fn ($value) => $this->isPlausibleAmount($value))
            ->map(fn ($value) => (float) $value)
            ->sortDesc()
            ->values();

        return $candidates->isNotEmpty() ? number_format((float) $candidates->first(), 2, '.', '') : null;
    }

    protected function isPlausibleAmount(?string $value): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        $amount = (float) $value;

        return $amount > 0 && $amount <= 100000000;
    }

    protected function extractBank(Collection $lines): ?string
    {
        $keywords = collect(Config::get('services.ocr.bank_keywords', []))
            ->map(fn ($item) => trim((string) $item))
            ->filter();

        foreach ($lines as $line) {
            foreach ($keywords as $keyword) {
                if (Str::contains($line, $this->normalizeForSearch($keyword))) {
                    return $this->canonicalBankName($keyword);
                }
            }
        }

        return null;
    }

    protected function canonicalBankName(string $value): string
    {
        $normalized = $this->normalizeForSearch($value);

        return match (true) {
            Str::contains($normalized, 'ECOBANK') => 'Ecobank',
            Str::contains($normalized, 'CORIS') => 'Coris Bank',
            default => Str::title(Str::lower($normalized)),
        };
    }

    protected function extractReferenceByLabels(Collection $lines, array $labels): ?string
    {
        foreach ($labels as $label) {
            foreach ($lines as $line) {
                if (! Str::contains($line, $label)) {
                    continue;
                }

                if (preg_match('/(?:REFERENCE|REF|BORDEREAU DE VERSEMENT ESPECES N|BORDEREAU|N)[^A-Z0-9]{0,8}([A-Z0-9\/\-_]{4,})/', $line, $matches)) {
                    return $matches[1];
                }
            }
        }

        return null;
    }

    protected function extractReference(Collection $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/(?:REFERENCE|REF|BORDEREAU|N)[^A-Z0-9]{0,8}([A-Z0-9\/\-_]{4,})/', $line, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    protected function extractContextLines(Collection $lines, array $labels): Collection
    {
        return $lines
            ->filter(function ($line) use ($labels) {
                foreach ($labels as $label) {
                    if (Str::contains($line, $label)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    protected function extractGare(string $normalized, Collection $availableGares, ?int $preferredGareId): array
    {
        if ($preferredGareId && $availableGares->count() === 1) {
            $gare = $availableGares->first();

            return [
                'gare_id' => $gare?->id,
                'gare_label' => $gare ? $gare->name.' — '.$gare->city : null,
                'confidence' => 1.0,
            ];
        }

        foreach ($availableGares as $gare) {
            $needles = array_filter([
                $this->normalizeForSearch($gare->code ?? ''),
                $this->normalizeForSearch($gare->name ?? ''),
                $this->normalizeForSearch($gare->city ?? ''),
                $this->normalizeForSearch($gare->address ?? ''),
            ]);

            foreach ($needles as $needle) {
                if ($needle !== '' && Str::contains($normalized, $needle)) {
                    return [
                        'gare_id' => $gare->id,
                        'gare_label' => $gare->name.' — '.$gare->city,
                        'confidence' => 0.82,
                    ];
                }
            }
        }

        return [];
    }

    protected function normalizeForSearch(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->upper()
            ->replace(["\t", "\r"], ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
    }

    protected function normalizeAmount(string $value): ?string
    {
        $value = trim(str_replace(["\u{00A0}", ' '], '', $value));

        if ($value === '') {
            return null;
        }

        $commaCount = substr_count($value, ',');
        $dotCount = substr_count($value, '.');

        if ($commaCount > 0 && $dotCount > 0) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');

            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($commaCount > 0) {
            $parts = explode(',', $value);

            if (count($parts) === 2 && strlen($parts[1]) === 2) {
                $value = $parts[0].'.'.$parts[1];
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($dotCount > 0) {
            $parts = explode('.', $value);

            if (! (count($parts) === 2 && strlen($parts[1]) === 2)) {
                $value = str_replace('.', '', $value);
            }
        }

        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
    }

    protected function toIsoDate(string $value): ?string
    {
        $value = str_replace(['.', '-'], '/', $value);

        foreach (['d/m/Y', 'd/m/y', 'Y/m/d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable $exception) {
                //
            }
        }

        return null;
    }

    protected function previewLines(string $rawText): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $rawText) ?: [])
            ->map(fn ($line) => trim($line))
            ->filter()
            ->take(12)
            ->values()
            ->all();
    }

    protected function resolveBinaryCandidates(array $configuredCandidates, array $commonWindowsPatterns = []): array
    {
        $candidates = [];

        foreach ($configuredCandidates as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '') {
                $candidates[] = $candidate;
            }
        }

        foreach ($commonWindowsPatterns as $pattern) {
            foreach ($this->expandBinaryPattern($pattern) as $match) {
                $candidates[] = $match;
            }
        }

        return array_values(array_unique($candidates));
    }

    protected function expandBinaryPattern(string $pattern): array
    {
        if (str_contains($pattern, '*')) {
            $matches = glob($pattern) ?: [];

            return array_values(array_filter($matches, fn ($path) => is_file($path)));
        }

        return is_file($pattern) ? [$pattern] : [];
    }

    protected function makeWorkingDirectory(): string
    {
        $directory = storage_path('app/private/tmp/ocr-work/'.Str::uuid());

        File::ensureDirectoryExists($directory);

        return $directory;
    }

    protected function runProcess(array $command): string
    {
        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput() ?: $process->getOutput() ?: 'Commande externe en échec.');
            throw new RuntimeException($error);
        }

        return $process->getOutput();
    }
}
