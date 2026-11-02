<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesDateTimeInput;
use App\Models\RideRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RideSearchRequest extends FormRequest
{
    use NormalizesDateTimeInput;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeDateTime('depart_at');
    }

    public function rules(): array
    {
        return [
            'origin_lat' => ['required', 'numeric', 'between:-90,90'],
            'origin_lng' => ['required', 'numeric', 'between:-180,180'],
            'dest_lat' => ['required', 'numeric', 'between:-90,90'],
            'dest_lng' => ['required', 'numeric', 'between:-180,180'],
            'depart_at' => ['required', 'date', 'after:now'],
            'seats_needed' => ['sometimes', 'integer', 'between:1,4'],
            'women_only' => ['sometimes', 'boolean'],
            'transport_preference' => ['sometimes', Rule::in([
                RideRequest::PREF_ANY,
                RideRequest::PREF_EXTERNAL,
                RideRequest::PREF_PRIVATE,
            ])],
            'limit' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }
}
