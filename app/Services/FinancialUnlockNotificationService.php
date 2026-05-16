<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Gare;
use App\Models\NotificationHistory;
use App\Models\User;
use Carbon\CarbonInterface;

class FinancialUnlockNotificationService
{
    public function notifyStationManagerForUnlock(
        string $scope,
        int $gareId,
        string $entryType,
        int $entryId,
        CarbonInterface|string|null $operationDate,
        CarbonInterface $unlockedUntil,
        ?string $reason,
        ?User $authorizer = null
    ): void {
        $recipientRole = $scope === 'courrier'
            ? UserRole::AgentCourrierGare->value
            : UserRole::ChefDeGare->value;

        $recipients = User::query()
            ->where('is_active', true)
            ->where('role', $recipientRole)
            ->where('gare_id', $gareId)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $gareName = Gare::query()->whereKey($gareId)->value('name') ?: 'Gare';
        $dateLabel = $operationDate instanceof CarbonInterface
            ? $operationDate->format('d/m/Y')
            : (filled($operationDate) ? (string) $operationDate : now('Africa/Abidjan')->toDateString());
        $entryLabel = match ($entryType) {
            'recette' => 'recette',
            'depense' => 'depense',
            default => 'versement bancaire',
        };

        foreach ($recipients as $recipient) {
            NotificationHistory::updateOrCreate(
                [
                    'user_id' => $recipient->id,
                    'type' => 'financial_entry_unlock_authorized',
                    'source_key' => "financial-unlock:{$scope}:{$entryType}:{$entryId}:{$recipient->id}",
                ],
                [
                    'subject' => 'Autorisation de modification accordee',
                    'content' => sprintf(
                        'Une autorisation de modification a ete accordee pour la %s de %s (date %s) jusqu au %s.%s%s',
                        $entryLabel,
                        $gareName,
                        $dateLabel,
                        $unlockedUntil->format('d/m/Y H:i'),
                        $reason ? ' Motif: '.$reason : '',
                        $authorizer?->name ? ' Autorise par: '.$authorizer->name.'.' : ''
                    ),
                    'status' => 'generated',
                    'control_date' => now('Africa/Abidjan')->toDateString(),
                    'concerned_date' => $operationDate instanceof CarbonInterface
                        ? $operationDate->toDateString()
                        : (filled($operationDate) ? (string) $operationDate : null),
                    'gares' => [$gareName],
                    'operations' => ['unlock', $entryType, $scope],
                    'payload' => [
                        'entry_type' => $entryType,
                        'entry_id' => $entryId,
                        'gare_id' => $gareId,
                        'scope' => $scope,
                        'unlocked_until' => $unlockedUntil->format(DATE_ATOM),
                        'reason' => $reason,
                        'authorizer_id' => $authorizer?->id,
                    ],
                ]
            );
        }
    }
}

