<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesDateTimeInput;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProviderJoinRequest extends FormRequest
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
            'vehicle_id' => [
                'required',
                'integer',
                Rule::exists('vehicles', 'id')
                    ->where('user_id', $this->user()->id)
                    ->where('status', Vehicle::STATUS_ACTIVE)
                    ->whereNull('deleted_at'),
            ],
            'message' => ['sometimes', 'nullable', 'string', 'max:300'],
            'start_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'start_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'intended_depart_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
