<?php

return [
    'ocr' => [
        'driver' => env('OCR_DRIVER', 'local_tesseract'),
        'enabled' => filter_var(env('OCR_ENABLED', true), FILTER_VALIDATE_BOOL),

        // Supporte les deux noms de variable.
        'tesseract_binary' => env('TESSERACT_PATH', env('TESSERACT_BINARY', 'tesseract')),

        // Extraction texte PDF directe si disponible.
        'pdf_text_binary' => env('PDF_TEXT_BINARY', 'pdftotext'),

        // Conversion PDF -> image pour OCR.
        'pdf_to_image_binary' => env('PDF_TO_IMAGE_BINARY', 'pdftoppm'),
        'pdf_to_image_cairo_binary' => env('PDF_TO_IMAGE_CAIRO_BINARY', 'pdftocairo'),
        'mutool_binary' => env('MUTOOL_BINARY', 'mutool'),
        'imagemagick_binary' => env('IMAGEMAGICK_BINARY', 'magick'),
        'ghostscript_binary' => env('GHOSTSCRIPT_BINARY', 'gswin64c'),

        'ocr_languages' => env('OCR_LANGUAGES', 'fra+eng'),
        'bank_keywords' => array_values(array_filter(array_map('trim', explode(',', env(
            'OCR_BANK_KEYWORDS',
            'ECOBANK,CORIS BANK,CORIS,NSIA BANQUE,NSIA,SGBCI,SGCI,BNI,UBA,BOA,BANK OF AFRICA,SIB,ORABANK,BICICI,ACCESS BANK,BDK'
        ))))),
    ],
];
