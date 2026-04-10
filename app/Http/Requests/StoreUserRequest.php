<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'role' => ['required', Rule::enum(UserRole::class)],
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
            $role = $this->input('role');

            if ($role === UserRole::ChefDeGare->value && ! $this->filled('gare_id')) {
                $validator->errors()->add('gare_id', 'Veuillez sélectionner une gare principale pour le chef de gare.');
            }

            if ($role === UserRole::Caissiere->value && ! $this->boolean('all_gares') && empty($this->input('zone_gares', []))) {
                $validator->errors()->add('zone_gares', 'Veuillez sélectionner au moins une gare pour la caissière.');
            }
        });
    }
}
