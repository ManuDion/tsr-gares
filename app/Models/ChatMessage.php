<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'content',
        'message_type',
        'audio_disk',
        'audio_path',
        'audio_mime_type',
        'audio_size',
    ];

    protected function casts(): array
    {
        return [
            'audio_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ChatMessage $message) {
            if ($message->audio_disk && $message->audio_path) {
                Storage::disk($message->audio_disk)->delete($message->audio_path);
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAudio(): bool
    {
        return $this->message_type === 'audio' && filled($this->audio_path);
    }
}
