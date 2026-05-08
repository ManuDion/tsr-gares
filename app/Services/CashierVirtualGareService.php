<?php

namespace App\Services;

use App\Models\Gare;
use App\Models\User;
use Illuminate\Support\Str;

class CashierVirtualGareService
{
    public function ensureForScope(User $user, string $scope): Gare
    {
        $label = $this->virtualName($user);
        $code = $this->virtualCode($user, $scope);

        $gare = Gare::query()->firstOrCreate(
            [
                'is_virtual' => true,
                'virtual_owner_user_id' => $user->id,
                'virtual_scope' => $scope,
            ],
            [
                'code' => $code,
                'name' => $label,
                'city' => 'Virtuelle',
                'zone' => strtoupper($scope),
                'address' => 'Gare virtuelle - caisse centrale',
                'versement_mode' => 'direct',
                'cashier_user_id' => $user->id,
                'is_active' => true,
            ]
        );

        if (! $user->gares()->where('gares.id', $gare->id)->exists()) {
            $user->gares()->syncWithoutDetaching([$gare->id]);
        }

        return $gare;
    }

    public function virtualName(User $user): string
    {
        return sprintf('%s_%s', $user->role?->value ?? 'role', Str::slug($user->name, '_'));
    }

    protected function virtualCode(User $user, string $scope): string
    {
        return sprintf('VIRT-%s-%s', strtoupper(substr($scope, 0, 3)), $user->id);
    }
}

