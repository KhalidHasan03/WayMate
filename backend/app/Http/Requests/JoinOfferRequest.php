<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesDateTimeInput;
use Illuminate\Foundation\Http\FormRequest;

class JoinOfferRequest extends FormRequest
{
    use NormalizesDateTimeInput;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeDateTime('intended_depart_at');
    }

    public function rules(): array
    {
        return [
            'seats' => ['sometimes', 'integer', 'between:1,4'],
            'message' => ['sometimes', 'nullable', 'string', 'max:300'],
            'start_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'start_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'intended_depart_at' => ['sometimes', 'nullable', 'date'],
            'consented_contribution_poisha' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
        ];
    }
}
