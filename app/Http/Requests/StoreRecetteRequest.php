<?php

namespace App\Http\Requests;

use App\Models\Gare;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecetteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scope = $this->input('module') === 'courrier' ? 'courrier' : 'gares';

        $user = $this->user();

        return $user?->canAccessFinancialScope($scope)
            && $user?->canActAsChefForScope($scope);
    }

    public function rules(): array
    {
        return [
            'gare_id' => ['nullable', 'integer', 'exists:gares,id'],
            'operation_date' => ['required', 'date'],
            'ticket_inter_amount' => ['required', 'numeric', 'min:0'],
            'ticket_national_amount' => ['required', 'numeric', 'min:0'],
            'bagage_inter_amount' => ['required', 'numeric', 'min:0'],
            'bagage_national_amount' => ['required', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'justificatif_name' => ['nullable', 'string', 'max:120'],
            'justificatif' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:'.(int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $scope = $this->input('module') === 'courrier' ? 'courrier' : 'gares';
            $user = $this->user();
            $gareId = $user?->canActAsChefForScope($scope)
                ? $user?->gare_id
                : $this->integer('gare_id');

            if (! $gareId) {
                return;
            }

            $gare = Gare::query()->find($gareId);
            if (! $gare || ! $gare->isInterOnly()) {
                return;
            }

            $ticketNational = (float) $this->input('ticket_national_amount', 0);
            $bagageNational = (float) $this->input('bagage_national_amount', 0);
            if ($ticketNational > 0 || $bagageNational > 0) {
                $validator->errors()->add('ticket_national_amount', 'Cette gare est en mode inter uniquement. Les montants nationaux doivent rester a 0.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'justificatif.required' => 'Le justificatif est obligatoire pour enregistrer une recette.',
        ];
    }
}
