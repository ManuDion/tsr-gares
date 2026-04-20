<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canCreateFinancialEntry() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $entries = collect($this->input('entries', []))
            ->filter(function ($entry, $index) {
                $hasText = collect($entry)->filter(fn ($value) => filled($value))->isNotEmpty();
                $hasFile = $this->hasFile("entries.$index.justificatif");

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
            'entries.*.amount' => ['required', 'numeric', 'min:0'],
            'entries.*.motif' => ['required', 'string', 'max:150'],
            'entries.*.reference' => ['nullable', 'string', 'max:100'],
            'entries.*.description' => ['nullable', 'string', 'max:500'],
            'entries.*.justificatif' => [
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
            'entries.required' => 'Ajoutez au moins une dépense.',
            'entries.max' => 'Le maximum est de 5 dépenses par enregistrement.',
        ];
    }
}
