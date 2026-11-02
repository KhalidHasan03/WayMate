<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $vehicle = $this->route('vehicle');

        return [
            'type' => [$required, Rule::in([
                Vehicle::TYPE_MOTORCYCLE,
                Vehicle::TYPE_SCOOTER,
                Vehicle::TYPE_CAR,
                'other',
            ])],
            'make' => [$required, 'string', 'max:60'],
            'model' => [$required, 'string', 'max:60'],
            'year' => ['sometimes', 'nullable', 'integer', 'between:1980,'.(now()->year + 1)],
            'registration_no' => [
                $required,
                'string',
                'max:30',
                Rule::unique('vehicles', 'registration_no')->ignore($vehicle?->id),
            ],
            'color' => ['sometimes', 'nullable', 'string', 'max:30'],
            'seat_capacity' => [$required, 'integer', 'between:1,6'],
        ];
    }
}
