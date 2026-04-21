<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVersementBancaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canCreateFinancialEntry() ?? false;
    }

    public function rules(): array
    {
        $max = (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120);

        return [
            'gare_id' => ['nullable', 'integer', 'exists:gares,id'],
            'operation_date' => ['required', 'date'],
            'receipt_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'bordereau_name' => ['nullable', 'string', 'max:120'],
            'bordereau' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:'.$max,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'bordereau.required' => 'Le bordereau est obligatoire pour enregistrer un versement.',
        ];
    }
}
