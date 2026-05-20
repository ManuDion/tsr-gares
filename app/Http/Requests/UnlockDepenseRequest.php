<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnlockDepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scope = (string) ($this->route('depense')?->service_scope ?? 'gares');

        return $this->user()?->canUnlockFinancialScope($scope) ?? false;
    }

    public function rules(): array
    {
        return [
            'unlock_reason' => ['required', 'string', 'max:255'],
            'unlock_duration' => ['required', 'integer', 'min:1', 'max:10000'],
            'unlock_unit' => ['required', 'in:minutes,hours,days'],
        ];
    }
}
