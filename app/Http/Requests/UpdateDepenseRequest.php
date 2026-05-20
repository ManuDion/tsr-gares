<?php

namespace App\Http\Requests;

use App\Support\JustificatifFileRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasFile('justificatif') && ! $this->hasFile('justificatifs')) {
            $this->files->set('justificatifs', [$this->file('justificatif')]);
        }

        if ($this->exists('amount')) {
            $value = (string) $this->input('amount');
            $normalized = preg_replace('/[^\d]/', '', $value ?? '');
            $this->merge([
                'amount' => $normalized === '' ? null : (int) $normalized,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'gare_id' => ['nullable', 'integer', 'exists:gares,id'],
            'operation_date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:0'],
            'motif' => ['required', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'justificatif_name' => ['nullable', 'string', 'max:120'],
            'history_comment' => ['nullable', 'string', 'max:255'],
            'justificatifs' => ['nullable', 'array', 'min:1'],
            'justificatifs.*' => JustificatifFileRules::single(false),
        ];
    }

    public function messages(): array
    {
        return [
            'justificatifs.*.file' => 'Le justificatif doit etre un fichier valide.',
            'justificatifs.*.uploaded' => 'Le televersement du justificatif a echoue. Veuillez reessayer.',
        ];
    }
}
