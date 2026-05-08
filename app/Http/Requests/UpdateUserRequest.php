<?php

namespace App\Http\Requests;

use App\Enums\ServiceModule;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->isAdmin() || $this->user()?->isResponsable()) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => ['nullable', Password::min(8)],
            'module' => ['nullable', Rule::in(array_map(fn (ServiceModule $module) => $module->value, ServiceModule::cases()))],
            'modules' => ['nullable', 'array', 'max:2'],
            'modules.*' => ['string', Rule::in(array_map(fn (ServiceModule $module) => $module->value, ServiceModule::cases()))],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, UserRole::cases()))],
            'gare_id' => ['nullable', 'integer', 'exists:gares,id'],
            'zone_gares' => ['nullable', 'array'],
            'zone_gares.*' => ['integer', 'exists:gares,id'],
            'all_gares' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'must_change_password' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $role = UserRole::fromLegacyAware((string) $this->input('role'));
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

            if ($role->isUniversalSupervisor()) {
                return;
            }

            if ($role->requiresPrimaryGare() && ! $this->filled('gare_id')) {
                $validator->errors()->add('gare_id', 'Veuillez selectionner une gare principale pour ce role.');
            }

            if ($role->supportsMultipleGares() && ! $this->boolean('all_gares') && empty($this->input('zone_gares', []))) {
                $validator->errors()->add('zone_gares', 'Veuillez selectionner au moins une gare pour ce profil.');
            }
        });
    }
}
