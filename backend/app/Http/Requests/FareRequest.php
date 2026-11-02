<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.label' => ['required', 'string', 'max:100'],
            'items.*.amount_poisha' => ['required', 'integer', 'min:0', 'max:10000000'],
            'items.*.split_method' => ['required', 'in:equal,provider_only'],
            'provider_participates' => ['required', 'boolean'],
        ];
    }
}
