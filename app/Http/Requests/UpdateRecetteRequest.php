<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecetteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $serviceScope = $this->route('recette')?->service_scope ?? 'gares';
        $isCourrier = $serviceScope === 'courrier';

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
            'history_comment' => ['nullable', 'string', 'max:255'],
            'justificatif' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:'.(int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120),
            ],
        ];
    }
}
