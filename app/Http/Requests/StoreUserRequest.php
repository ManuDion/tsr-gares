<?php

namespace App\Http\Requests;

use App\Enums\ServiceModule;
use App\Enums\UserRole;
use App\Models\Gare;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->hasGlobalVisibility() || $this->user()?->isServiceAdmin()) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'contract_type' => ['nullable', 'string', 'max:120'],
            'assignment_location' => ['nullable', 'string', 'max:180', Rule::exists('gares', 'name')],
            'hr_service' => ['nullable', 'string', 'max:120'],
            'module' => ['nullable', Rule::in(array_map(fn (ServiceModule $module) => $module->value, ServiceModule::cases()))],
            'modules' => ['nullable', 'array', 'max:2'],
            'modules.*' => ['string', Rule::in(array_map(fn (ServiceModule $module) => $module->value, ServiceModule::cases()))],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, UserRole::cases()))],
            'gare_id' => ['nullable', 'integer', 'exists:gares,id'],
            'zone_gares' => ['nullable', 'array'],
            'zone_gares.*' => ['integer', 'exists:gares,id'],
            'all_gares' => ['nullable', 'boolean'],
            'allow_multi_gare_entry' => ['nullable', 'boolean'],
            'cashier_collection_mode' => ['nullable', Rule::in(User::cashierCollectionModes())],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $actor = $this->user();
            $roleValue = (string) $this->input('role');
            if ($roleValue === '') {
                return;
            }

            try {
                $role = UserRole::fromLegacyAware($roleValue);
            } catch (\ValueError) {
                return;
            }
            $module = ServiceModule::tryFrom((string) ($this->input('module') ?? ''));
            $modules = collect($this->input('modules', []))->filter()->unique()->values();

            if ($module && ! $modules->contains($module->value)) {
                $modules->prepend($module->value);
            }

            if (! $module && ! $role->isUniversalSupervisor()) {
                $validator->errors()->add('module', 'Veuillez selectionner un service pour ce role.');

                return;
            }

            if ($module) {
                $allowedRoleValues = collect($module->roleOptions())->pluck('value')->all();
                if (! in_array($role->value, $allowedRoleValues, true)) {
                    $validator->errors()->add('role', 'Le role choisi ne correspond pas au service selectionne.');
                }
            }

            if ($modules->count() > 2) {
                $validator->errors()->add('modules', 'Un utilisateur peut etre rattache a deux services maximum.');
            }

            if ($modules->contains(ServiceModule::Documents->value) && $modules->contains(ServiceModule::Rh->value)) {
                $validator->errors()->add('modules', 'La combinaison Documents + RH n\'est pas autorisee pour ce flux.');
            }

            if ($actor?->isServiceAdmin()) {
                $actorModule = $actor->assignedModule();
                if (! $actorModule) {
                    $validator->errors()->add('module', 'Votre compte administrateur n\'est rattache a aucun service.');

                    return;
                }

                if (! $module || $module !== $actorModule) {
                    $validator->errors()->add('module', 'Vous pouvez creer des utilisateurs uniquement dans votre service.');
                }

                if ($modules->contains(fn ($value) => $value !== $actorModule->value)) {
                    $validator->errors()->add('modules', 'Les services rattaches doivent rester dans votre perimetre.');
                }

                if ($role->isUniversalSupervisor()) {
                    $validator->errors()->add('role', 'La creation de superviseurs universels est reservee a l\'administration generale.');
                }

                if ($role->module() && $role->module() !== $actorModule) {
                    $validator->errors()->add('role', 'Le role choisi ne correspond pas a votre service.');
                }
            }

            if ($modules->contains(ServiceModule::Courrier->value)) {
                $gareIdsToCheck = [];
                if ($this->filled('gare_id')) {
                    $gareIdsToCheck[] = (int) $this->input('gare_id');
                }
                $gareIdsToCheck = array_merge(
                    $gareIdsToCheck,
                    collect($this->input('zone_gares', []))->map(fn ($id) => (int) $id)->all()
                );

                if (! empty($gareIdsToCheck)) {
                    $invalidGares = Gare::query()
                        ->whereIn('id', array_unique($gareIdsToCheck))
                        ->where('activity_mode', 'inter_only')
                        ->pluck('name')
                        ->all();

                    if (! empty($invalidGares)) {
                        $message = 'Les gares en mode inter uniquement ne peuvent pas etre rattachees au service courrier.';
                        $validator->errors()->add('gare_id', $message);
                        $validator->errors()->add('zone_gares', $message);
                    }
                }
            }

            if ($role->isUniversalSupervisor()) {
                return;
            }

            if ($role->requiresPrimaryGare() && ! $this->filled('gare_id')) {
                $validator->errors()->add('gare_id', 'Veuillez selectionner une gare principale pour ce role.');
            }

            $supportsMultiAssignment = $role->supportsMultipleGares() || $this->boolean('allow_multi_gare_entry');
            if ($supportsMultiAssignment && ! $this->boolean('all_gares') && empty($this->input('zone_gares', []))) {
                $validator->errors()->add('zone_gares', 'Veuillez selectionner au moins une gare pour ce profil.');
            }

            $cashierRoleValues = [
                UserRole::CaissierGare->value,
                UserRole::CaissierCourrier->value,
                UserRole::Caissiere->value,
                UserRole::ChefDeZone->value,
            ];
            if (in_array($role->value, $cashierRoleValues, true)) {
                $mode = (string) $this->input('cashier_collection_mode', User::CASHIER_COLLECTION_BOTH);
                if (! in_array($mode, User::cashierCollectionModes(), true)) {
                    $validator->errors()->add('cashier_collection_mode', 'Le mode de collecte des recettes du caissier est invalide.');
                }
            }
        });
    }
}
