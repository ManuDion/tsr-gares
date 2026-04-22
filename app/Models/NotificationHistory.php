<?php

namespace App\Models;

use App\Enums\ServiceModule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'source_key',
        'subject',
        'content',
        'status',
        'control_date',
        'concerned_date',
        'gares',
        'operations',
        'payload',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'control_date' => 'date',
            'concerned_date' => 'date',
            'gares' => 'array',
            'operations' => 'array',
            'payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForModule($query, ServiceModule $module)
    {
        return match ($module) {
            ServiceModule::Gares => $query->whereJsonContains('operations', ServiceModule::Gares->value),
            ServiceModule::Courrier => $query->whereJsonContains('operations', ServiceModule::Courrier->value),
            ServiceModule::Documents => $query->where(function ($builder) {
                $builder->whereIn('type', ['document_expired', 'document_expiry_daily', 'document_expiry_weekly'])
                    ->orWhereJsonContains('operations', 'documents_administratifs')
                    ->orWhereJsonContains('operations', ServiceModule::Documents->value)
                    ->orWhere('payload->module', ServiceModule::Documents->value);
            }),
            ServiceModule::Rh => $query->where(function ($builder) {
                $builder->where('type', 'like', 'rh_%')
                    ->orWhereJsonContains('operations', ServiceModule::Rh->value)
                    ->orWhere('payload->module', ServiceModule::Rh->value);
            }),
        };
    }
}
