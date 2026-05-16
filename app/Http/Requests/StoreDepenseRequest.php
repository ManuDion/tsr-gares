<?php

namespace App\Http\Requests;

use App\Support\JustificatifFileRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreDepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scope = $this->input('module') === 'courrier' ? 'courrier' : 'gares';

        $user = $this->user();

        return $user?->canAccessFinancialScope($scope)
            && ($user?->canActAsChefForScope($scope) || $user?->canActAsCashierForScope($scope));
    }

    protected function prepareForValidation(): void
    {
        $entries = collect($this->input('entries', []))
            ->map(function ($entry) {
                if (! is_array($entry)) {
                    return $entry;
                }

                $amount = (string) data_get($entry, 'amount', '');
                $normalized = preg_replace('/[^\d]/', '', $amount);
                if ($normalized !== '') {
                    $entry['amount'] = (int) $normalized;
                }

                return $entry;
            })
            ->filter(function ($entry, $index) {
                $hasText = collect($entry)->filter(fn ($value) => filled($value))->isNotEmpty();
                $hasFile = $this->hasFile("entries.$index.justificatif")
                    || $this->hasFile("entries.$index.justificatifs");

                return $hasText || $hasFile;
            })
            ->all();

        $this->merge(['entries' => $entries]);
    }

    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1', 'max:5'],
            'entries.*.gare_id' => ['nullable', 'integer', 'exists:gares,id'],
            'entries.*.operation_date' => ['required', 'date'],
            'entries.*.amount' => ['required', 'integer', 'min:0'],
            'entries.*.motif' => ['required', 'string', 'max:150'],
            'entries.*.reference' => ['nullable', 'string', 'max:100'],
            'entries.*.description' => ['nullable', 'string', 'max:500'],
            'entries.*.justificatif_name' => ['nullable', 'string', 'max:120'],
            'entries.*.justificatifs' => ['required', 'array', 'min:1', 'max:10'],
            'entries.*.justificatifs.*' => JustificatifFileRules::single(true),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $scope = $this->input('module') === 'courrier' ? 'courrier' : 'gares';
            $user = $this->user();

            if (! ($user?->canActAsChefForScope($scope) && $user?->canUseMultiGareEntry())) {
                return;
            }

            foreach ((array) $this->input('entries', []) as $index => $entry) {
                if (blank(data_get($entry, 'gare_id'))) {
                    $validator->errors()->add("entries.$index.gare_id", 'Veuillez selectionner une gare pour cette depense.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'entries.required' => 'Ajoutez au moins une depense.',
            'entries.max' => 'Le maximum est de 5 depenses par enregistrement.',
            'entries.*.justificatifs.required' => 'Le justificatif est obligatoire pour chaque depense.',
            'entries.*.justificatifs.max' => 'Maximum 10 photos/fichiers justificatifs par depense.',
            'entries.*.justificatifs.*.uploaded' => 'Le televersement du justificatif a echoue. Veuillez reessayer.',
        ];
    }
}
