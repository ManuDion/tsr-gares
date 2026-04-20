<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AdministrativeDocument;
use App\Models\NotificationHistory;
use App\Models\User;
use Carbon\Carbon;

class DocumentExpiryService
{
    public function ensureFreshAlerts(?Carbon $today = null): void
    {
        $today = ($today ?: now('Africa/Abidjan'))->startOfDay();
        $users = User::query()
            ->where('is_active', true)
            ->whereIn('role', [
                UserRole::Admin->value,
                UserRole::Responsable->value,
                UserRole::Controleur->value,
            ])
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        AdministrativeDocument::query()
            ->where('is_active', true)
            ->get()
            ->each(function (AdministrativeDocument $document) use ($today, $users) {
                $daysRemaining = $today->diffInDays($document->expires_at, false);

                if ($daysRemaining > 30) {
                    return;
                }

                $isExpired = $daysRemaining < 0;
                $shouldSendWeekly = in_array($daysRemaining, [30, 23, 16, 9], true);
                $shouldSendDaily = $daysRemaining <= 7;

                if (! $shouldSendWeekly && ! $shouldSendDaily && ! $isExpired) {
                    return;
                }

                foreach ($users as $user) {
                    if ($isExpired) {
                        NotificationHistory::updateOrCreate(
                            [
                                'user_id' => $user->id,
                                'type' => 'document_expired',
                                'source_key' => 'document-expired:'.$document->id,
                                'concerned_date' => $document->expires_at?->toDateString(),
                            ],
                            $this->payload(
                                $document,
                                'Document administratif expiré',
                                sprintf(
                                    'Le document %s (%s) a expiré le %s. Le rappel restera visible tant que le document n’aura pas été mis à jour.',
                                    $document->document_type,
                                    $document->original_name,
                                    $document->expires_at?->format('d/m/Y')
                                ),
                                $today
                            )
                        );

                        continue;
                    }

                    $type = $daysRemaining <= 7 ? 'document_expiry_daily' : 'document_expiry_weekly';

                    NotificationHistory::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'type' => $type,
                            'source_key' => 'document-alert:'.$document->id.':'.$type.':'.$today->toDateString(),
                            'concerned_date' => $document->expires_at?->toDateString(),
                            'control_date' => $today->toDateString(),
                        ],
                        $this->payload(
                            $document,
                            'Échéance administrative à anticiper',
                            sprintf(
                                'Le document %s (%s) expire le %s. Pensez à renouveler ce document avant son échéance.',
                                $document->document_type,
                                $document->original_name,
                                $document->expires_at?->format('d/m/Y')
                            ),
                            $today
                        )
                    );
                }
            });
    }

    public function clearPersistentAlerts(AdministrativeDocument $document): void
    {
        NotificationHistory::query()
            ->whereIn('type', ['document_expired', 'document_expiry_daily', 'document_expiry_weekly'])
            ->where(function ($query) use ($document) {
                $query->where('source_key', 'document-expired:'.$document->id)
                    ->orWhere('source_key', 'like', 'document-alert:'.$document->id.':%');
            })
            ->delete();
    }

    protected function payload(AdministrativeDocument $document, string $subject, string $content, Carbon $today): array
    {
        return [
            'subject' => $subject,
            'content' => $content,
            'status' => 'generated',
            'gares' => [],
            'operations' => ['documents_administratifs'],
            'payload' => [
                'document_id' => $document->id,
                'document_type' => $document->document_type,
                'file_name' => $document->original_name,
                'expires_at' => $document->expires_at?->toDateString(),
                'label' => $document->label,
            ],
        ];
    }
}
