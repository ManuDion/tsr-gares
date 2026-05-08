<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('gares', 'code')->ignore($this->route('gare'))],
            'name' => ['required', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:120'],
            'zone' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'versement_mode' => ['required', 'in:direct,cashier'],
            'cashier_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'activity_mode' => ['required', 'in:mixed,inter_only'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('versement_mode') === 'cashier' && ! $this->filled('cashier_user_id')) {
                $validator->errors()->add('cashier_user_id', 'Veuillez selectionner un caissier responsable pour cette gare.');
            }
        });
    }
}
