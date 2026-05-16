<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashierReceiptConfirmationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['received_total', 'received_inter_total', 'received_national_total'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = (string) $this->input($field);
            $normalized = preg_replace('/[^\d]/', '', $value ?? '');
            $this->merge([$field => $normalized === '' ? 0 : (int) $normalized]);
        }
    }

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
            'received_total' => ['required', 'integer', 'min:0'],
            'received_inter_total' => ['required', 'integer', 'min:0'],
            'received_national_total' => ['required', 'integer', 'min:0'],
            'mode' => ['nullable', 'in:validate,unlock'],
            'unlock_duration' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'unlock_unit' => ['nullable', 'in:minutes,hours,days'],
            'is_verified' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $scope = $this->input('module') === 'courrier' ? 'courrier' : 'gares';
            $gare = \App\Models\Gare::query()->find($this->integer('gare_id'));
            if (! $gare) {
                return;
            }

            $operationDate = $this->date('operation_date')?->toDateString() ?? now('Africa/Abidjan')->toDateString();
            $expected = app(\App\Services\CashierFlowService::class)->expectedForGareDate($gare, $scope, $operationDate, $this->user());

            $receivedInter = (int) $this->input('received_inter_total', 0);
            $receivedNational = (int) $this->input('received_national_total', 0);
            $receivedTotal = (int) $this->input('received_total', 0);
            $calculatedTotal = $receivedInter + $receivedNational;
            $cashier = $this->user();

            if ($cashier && ! $cashier->cashierCollectsInter() && $receivedInter !== 0) {
                $validator->errors()->add('received_inter_total', 'Ce caissier ne collecte pas les recettes internationales.');
            }

            if ($cashier && ! $cashier->cashierCollectsNational() && $receivedNational !== 0) {
                $validator->errors()->add('received_national_total', 'Ce caissier ne collecte pas les recettes nationales.');
            }

            if ($receivedTotal !== $calculatedTotal) {
                $validator->errors()->add('received_total', 'Le total recu doit etre egal a Inter + National (montants en FCFA).');
            }

            $mode = (string) $this->input('mode', 'validate');

            if ($mode === 'unlock') {
                if (! $this->filled('unlock_duration')) {
                    $validator->errors()->add('unlock_duration', 'Veuillez definir la duree de deverrouillage.');
                }
                if (! $this->filled('unlock_unit')) {
                    $validator->errors()->add('unlock_unit', 'Veuillez choisir l unite de deverrouillage.');
                }
            }
        });
    }
}
