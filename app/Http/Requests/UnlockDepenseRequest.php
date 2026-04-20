<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnlockDepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isResponsable();
    }

    public function rules(): array
    {
        return [
            'unlock_reason' => ['required', 'string', 'max:255'],
        ];
    }
}
