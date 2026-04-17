<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityLogService
{
    public function log(?User $user, string $eventType, Model|string|null $subject, string $description, array $context = []): void
    {
        if ($this->shouldSkipLog($context)) {
            return;
        }

        $model = $subject instanceof Model ? $subject : null;
        $subjectLabel = $context['subject'] ?? $this->resolveSubjectLabel($subject, $model);

        ActivityLog::create([
            'user_id' => $user?->id,
            'gare_id' => $context['gare_id'] ?? ($model && isset($model->gare_id) ? $model->gare_id : null),
            'event_type' => $eventType,
            'entity_type' => $context['entity_type'] ?? ($model ? class_basename($model) : (is_string($subject) ? $subject : null)),
            'entity_id' => $context['entity_id'] ?? ($model?->getKey()),
            'subject' => $subjectLabel,
            'description' => $description,
            'before' => $context['before'] ?? null,
            'after' => $context['after'] ?? null,
            'meta' => array_filter([
                'ip' => request()?->ip(),
                'url' => request()?->fullUrl(),
                'notes' => $context['notes'] ?? null,
                'extra' => $context['extra'] ?? null,
            ], fn ($value) => ! is_null($value)),
        ]);
    }


    protected function shouldSkipLog(array $context): bool
    {
        if (! array_key_exists('before', $context) || ! array_key_exists('after', $context)) {
            return false;
        }

        return $this->normalizePayload($context['before']) === $this->normalizePayload($context['after']);
    }

    protected function normalizePayload(mixed $payload): string
    {
        if (is_array($payload)) {
            ksort($payload);

            return json_encode(collect($payload)
                ->map(fn ($value) => $this->normalizePayload($value))
                ->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_bool($payload)) {
            return $payload ? '1' : '0';
        }

        if ($payload === null) {
            return 'null';
        }

        return trim((string) $payload);
    }

    protected function resolveSubjectLabel(Model|string|null $subject, ?Model $model = null): string
    {
        if ($subject instanceof Model) {
            return match (true) {
                isset($subject->name) => (string) $subject->name,
                isset($subject->email) => (string) $subject->email,
                isset($subject->reference) && $subject->reference => (string) $subject->reference,
                default => class_basename($subject).' #'.$subject->getKey(),
            };
        }

        if (is_string($subject) && $subject !== '') {
            return Str::headline($subject);
        }

        if ($model) {
            return class_basename($model).' #'.$model->getKey();
        }

        return 'Événement système';
    }
}
