<?php

namespace App\Http\Requests;

use App\Support\ModuleContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecetteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canCreateFinancialEntry() ?? false;
    }

    public function rules(): array
    {
        $module = ModuleContext::fromRequest($this, $this->user());
        $isCourrier = $module->financialScope() === 'courrier';

        return [
            'gare_id' => ['nullable', 'integer', 'exists:gares,id'],
            'operation_date' => ['required', 'date'],
            'ticket_inter_amount' => [$isCourrier ? 'nullable' : 'required', 'numeric', 'min:0'],
            'ticket_national_amount' => [$isCourrier ? 'nullable' : 'required', 'numeric', 'min:0'],
            'bagage_inter_amount' => [$isCourrier ? 'nullable' : 'required', 'numeric', 'min:0'],
            'bagage_national_amount' => [$isCourrier ? 'nullable' : 'required', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'justificatif_name' => ['nullable', 'string', 'max:120'],
            'justificatif' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:'.(int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Le montant total calculé est obligatoire.',
            'justificatif.mimes' => 'Le justificatif doit être un fichier PDF, JPG ou PNG.',
        ];
    }
}
