<?php

namespace App\Http\Requests;

use App\Models\Gare;
use App\Models\Recette;
use App\Support\JustificatifFileRules;
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
            'ticket_inter_amount' => ['nullable', 'integer', 'min:0'],
            'ticket_national_amount' => ['nullable', 'integer', 'min:0'],
            'bagage_inter_amount' => ['nullable', 'integer', 'min:0'],
            'bagage_national_amount' => ['nullable', 'integer', 'min:0'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'justificatif_name' => ['nullable', 'string', 'max:120'],
            'justificatifs' => ['required', 'array', 'min:1', 'max:10'],
            'justificatifs.*' => JustificatifFileRules::single(true),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasFile('justificatif') && ! $this->hasFile('justificatifs')) {
            $this->files->set('justificatifs', [$this->file('justificatif')]);
        }

        foreach ([
            'ticket_inter_amount',
            'ticket_national_amount',
            'bagage_inter_amount',
            'bagage_national_amount',
            'amount',
        ] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = (string) $this->input($field);
            $normalized = preg_replace('/[^\d]/', '', $value ?? '');
            if ($normalized === '') {
                continue;
            }

            $this->merge([$field => (int) $normalized]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $scope = $this->input('module') === 'courrier' ? 'courrier' : 'gares';
            $user = $this->user();

            if ($user?->canActAsChefForScope($scope) && $user?->canUseMultiGareEntry() && ! $this->filled('gare_id')) {
                $validator->errors()->add('gare_id', 'Veuillez selectionner une gare pour cette saisie.');
                return;
            }

            $gareId = $user?->canActAsChefForScope($scope)
                ? ($user?->canUseMultiGareEntry() ? $this->integer('gare_id') : $user?->gare_id)
                : $this->integer('gare_id');

            $operationDate = $this->input('operation_date');
            if (! $gareId || ! $operationDate) {
                return;
            }

            $alreadyExists = Recette::query()
                ->where('service_scope', $scope)
                ->where('gare_id', $gareId)
                ->whereDate('operation_date', $operationDate)
                ->exists();

            if ($alreadyExists) {
                $validator->errors()->add('operation_date', 'Une recette existe deja pour cette gare a cette date.');
            }

            $gare = Gare::query()->find($gareId);
            if (! $gare) {
                return;
            }

            if ($gare->isInterOnly()) {
                $ticketNational = (int) $this->input('ticket_national_amount', 0);
                $bagageNational = (int) $this->input('bagage_national_amount', 0);
                if ($ticketNational > 0 || $bagageNational > 0) {
                    $validator->errors()->add('ticket_national_amount', 'Cette gare est en mode inter uniquement. Les montants nationaux doivent rester a 0.');
                }
            }

            if ($gare->isNationalOnly()) {
                $ticketInter = (int) $this->input('ticket_inter_amount', 0);
                $bagageInter = (int) $this->input('bagage_inter_amount', 0);
                if ($ticketInter > 0 || $bagageInter > 0) {
                    $validator->errors()->add('ticket_inter_amount', 'Cette gare est en mode national uniquement. Les montants inter doivent rester a 0.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'justificatifs.required' => 'Le justificatif est obligatoire pour enregistrer une recette.',
            'justificatifs.max' => 'Vous pouvez joindre au maximum 10 photos/fichiers justificatifs par recette.',
            'justificatifs.*.uploaded' => 'Le televersement du justificatif a echoue. Veuillez reessayer.',
        ];
    }
}
