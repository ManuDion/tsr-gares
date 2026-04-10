<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersementBancaireHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'versement_bancaire_id',
        'modified_by',
        'before',
        'after',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function versement(): BelongsTo
    {
        return $this->belongsTo(VersementBancaire::class, 'versement_bancaire_id');
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }
}
