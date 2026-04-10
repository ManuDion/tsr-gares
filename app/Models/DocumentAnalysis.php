<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'piece_justificative_id',
        'status',
        'provider',
        'extracted_data',
        'confidence',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
            'confidence' => 'array',
            'raw_payload' => 'array',
        ];
    }

    public function justificative(): BelongsTo
    {
        return $this->belongsTo(PieceJustificative::class, 'piece_justificative_id');
    }
}
