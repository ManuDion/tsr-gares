<?php

namespace App\Http\Requests;

use App\Models\Gare;
use App\Models\VersementBancaire;
use App\Support\JustificatifFileRules;
use App\Services\BankRoutingService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVersementBancaireRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->hasFile('bordereau') && ! $this->hasFile('bordereaux')) {
            $this->files->set('bordereaux', [$this->file('bordereau')]);
        }

        if (! $this->exists('amount')) {
            return;
        }

        $value = (string) $this->input('amount');
        $normalized = preg_replace('/[^\d]/', '', $value ?? '');
        $this->merge([
            'amount' => $normalized === '' ? null : (int) $normalized,
        ]);
    }

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
            'amount' => ['required', 'integer', 'min:0'],
            'account_type' => ['required', 'in:inter,national'],
            'reference' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'bordereau_name' => ['nullable', 'string', 'max:120'],
            'bordereaux' => ['nullable', 'array', 'min:1'],
            'bordereaux.*' => JustificatifFileRules::single(false),
            'history_comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var VersementBancaire|null $versement */
            $versement = $this->route('versement');
            $requestedGareId = $this->filled('gare_id') ? $this->integer('gare_id') : null;
            $gareId = $requestedGareId && $requestedGareId > 0
                ? $requestedGareId
                : (int) ($versement?->gare_id ?? 0);
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

            $scope = $this->input('module') === 'courrier' ? 'courrier' : ((string) ($versement?->service_scope ?? 'gares'));
            $operationDate = $this->input('operation_date') ?: ($versement?->operation_date?->toDateString() ?? now('Africa/Abidjan')->toDateString());
            $forced = app(BankRoutingService::class)->forcedAccountTypeForDate($scope, $operationDate, $gareId);

            if ($gare->isInterOnly() && ! $forced && $this->input('account_type') !== 'inter') {
                $validator->errors()->add('account_type', 'Cette gare est en mode inter uniquement. Le compte inter est obligatoire.');
            }

            if ($gare->isNationalOnly() && ! $forced && $this->input('account_type') !== 'national') {
                $validator->errors()->add('account_type', 'Cette gare est en mode national uniquement. Le compte national est obligatoire.');
            }

            if ($forced && $this->input('account_type') !== $forced) {
                $label = $forced === 'inter' ? 'Ecobank' : 'Coris Bank';
                $validator->errors()->add('account_type', "Pour cette periode, seul le compte {$label} est autorise.");
            }
        });
    }

    public function messages(): array
    {
        return [
            'bordereaux.*.file' => 'Le bordereau doit etre un fichier valide.',
            'bordereaux.*.uploaded' => 'Le televersement du bordereau a echoue. Veuillez reessayer.',
        ];
    }
}
