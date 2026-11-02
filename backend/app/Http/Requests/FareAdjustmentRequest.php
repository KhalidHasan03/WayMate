<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FareAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_total_poisha' => ['required', 'integer', 'min:1', 'max:10000000'],
            'reason' => ['nullable', 'string', 'max:300'],
        ];
    }
}
