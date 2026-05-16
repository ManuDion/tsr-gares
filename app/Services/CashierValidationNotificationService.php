<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Gare;
use App\Models\NotificationHistory;
use App\Models\User;
use Carbon\Carbon;

class CashierValidationNotificationService
{
    public function notifyStationManagerForValidation(
        string $scope,
        Gare $gare,
        string $operationDate,
        User $cashier
    ): void {
        $recipientRole = $scope === 'courrier'
            ? UserRole::AgentCourrierGare->value
            : UserRole::ChefDeGare->value;

        $recipients = User::query()
            ->where('is_active', true)
            ->where('role', $recipientRole)
            ->where('gare_id', $gare->id)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $gareName = (string) ($gare->name ?: 'Gare');
        $dateLabel = Carbon::parse($operationDate, 'Africa/Abidjan')->format('d/m/Y');

        foreach ($recipients as $recipient) {
            NotificationHistory::updateOrCreate(
                [
                    'user_id' => $recipient->id,
                    'type' => 'cashier_receipt_validated',
                    'source_key' => "cashier-receipt-validated:{$scope}:{$gare->id}:{$operationDate}:{$recipient->id}",
                ],
                [
                    'subject' => 'Validation caissier enregistree',
                    'content' => sprintf(
                        'Les recettes de %s pour la date du %s ont ete validees par le caissier %s.',
                        $gareName,
                        $dateLabel,
                        (string) ($cashier->name ?: 'N/A')
                    ),
                    'status' => 'generated',
                    'control_date' => now('Africa/Abidjan')->toDateString(),
                    'concerned_date' => $operationDate,
                    'gares' => [$gareName],
                    'operations' => ['validation_caissier', 'recette', $scope],
                    'payload' => [
                        'scope' => $scope,
                        'gare_id' => (int) $gare->id,
                        'cashier_id' => (int) $cashier->id,
                        'operation_date' => $operationDate,
                    ],
                ]
            );
        }
    }
}
