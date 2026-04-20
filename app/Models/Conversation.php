<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_group',
        'created_by',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('last_read_at')->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function displayNameFor(User $user): string
    {
        if ($this->is_group) {
            if ($this->name) {
                return $this->name;
            }

            $names = $this->participants
                ->where('id', '!=', $user->id)
                ->pluck('name')
                ->take(3)
                ->implode(', ');

            return $names ? 'Discussion : '.$names : 'Discussion de groupe';
        }

        $other = $this->participants->firstWhere('id', '!=', $user->id);

        return $other?->name ?: ($this->name ?: 'Conversation privée');
    }

    public function unreadMessagesCountFor(User $user): int
    {
        $pivot = $this->participants->firstWhere('id', $user->id)?->pivot;
        $lastReadAt = $pivot?->last_read_at;

        return $this->messages()
            ->where('user_id', '!=', $user->id)
            ->when($lastReadAt, fn ($query) => $query->where('created_at', '>', $lastReadAt))
            ->count();
    }
}
