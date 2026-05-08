<?php

namespace App\Http\Requests;

use App\Models\Gare;
use App\Models\VersementBancaire;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVersementBancaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'gare_id' => ['nullable', 'integer', 'exists:gares,id'],
            'operation_date' => ['required', 'date'],
            'receipt_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'account_type' => ['required', 'in:inter,national'],
            'reference' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'bordereau_name' => ['nullable', 'string', 'max:120'],
            'bordereau' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:'.(int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120),
            ],
            'history_comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var VersementBancaire|null $versement */
            $versement = $this->route('versement');
            $gareId = $this->integer('gare_id', (int) ($versement?->gare_id ?? 0));
            if (! $gareId) {
                return;
            }

            $gare = Gare::query()->find($gareId);
            if (! $gare) {
                return;
            }

            if (! $gare->is_virtual && ($gare->versement_mode ?? 'direct') === 'cashier') {
                $validator->errors()->add('gare_id', 'Cette gare est rattachee a un caissier. Le versement se fait uniquement au niveau du caissier.');
            }

            if ($gare->isInterOnly() && $this->input('account_type') !== 'inter') {
                $validator->errors()->add('account_type', 'Cette gare est en mode inter uniquement. Le compte inter est obligatoire.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'bordereau.required' => 'Le bordereau est obligatoire pour modifier un versement.',
        ];
    }
}
