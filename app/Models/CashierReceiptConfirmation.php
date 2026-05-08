<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierReceiptConfirmation extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_scope',
        'gare_id',
        'cashier_id',
        'operation_date',
        'expected_total',
        'expected_inter_total',
        'expected_national_total',
        'received_total',
        'received_inter_total',
        'received_national_total',
        'is_verified',
        'verified_at',
        'verified_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'operation_date' => 'date',
            'expected_total' => 'decimal:2',
            'expected_inter_total' => 'decimal:2',
            'expected_national_total' => 'decimal:2',
            'received_total' => 'decimal:2',
            'received_inter_total' => 'decimal:2',
            'received_national_total' => 'decimal:2',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}

