<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashierReceiptConfirmationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scope = $this->input('module') === 'courrier' ? 'courrier' : 'gares';

        return $this->user()?->canActAsCashierForScope($scope) ?? false;
    }

    public function rules(): array
    {
        return [
            'gare_id' => ['required', 'integer', 'exists:gares,id'],
            'operation_date' => ['required', 'date'],
            'received_total' => ['required', 'numeric', 'min:0'],
            'received_inter_total' => ['required', 'numeric', 'min:0'],
            'received_national_total' => ['required', 'numeric', 'min:0'],
            'is_verified' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
