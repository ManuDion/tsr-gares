<?php

namespace App\Http\Requests;

use App\Enums\ServiceModule;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'module' => ['required', Rule::in(array_map(fn (ServiceModule $module) => $module->value, ServiceModule::cases()))],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, UserRole::cases()))],
            'gare_id' => ['nullable', 'integer', 'exists:gares,id'],
            'zone_gares' => ['nullable', 'array'],
            'zone_gares.*' => ['integer', 'exists:gares,id'],
            'all_gares' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $role = UserRole::fromLegacyAware((string) $this->input('role'));
            $module = ServiceModule::tryFrom((string) $this->input('module'));

            if (! $module) {
                return;
            }

            $allowedRoleValues = collect($module->roleOptions())->pluck('value')->all();
            if (! in_array($role->value, $allowedRoleValues, true)) {
                $validator->errors()->add('role', 'Le rôle choisi ne correspond pas au service sélectionné.');
            }

            if ($role->requiresPrimaryGare() && ! $this->filled('gare_id')) {
                $validator->errors()->add('gare_id', 'Veuillez sélectionner une gare principale pour ce rôle.');
            }

            if ($role->supportsMultipleGares() && ! $this->boolean('all_gares') && empty($this->input('zone_gares', []))) {
                $validator->errors()->add('zone_gares', 'Veuillez sélectionner au moins une gare pour ce profil.');
            }
        });
    }
}
