<?php

namespace App\Http\Requests;

use App\Support\JustificatifFileRules;
use Illuminate\Foundation\Http\FormRequest;

class AnalyzeVersementBordereauRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canCreateFinancialEntry() ?? false;
    }

    public function rules(): array
    {
        return [
            'bordereau' => JustificatifFileRules::single(true),
        ];
    }

    public function messages(): array
    {
        return [
            'bordereau.uploaded' => 'Le televersement du bordereau a echoue. Veuillez reessayer.',
        ];
    }
}
