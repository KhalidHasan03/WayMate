<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesDateTimeInput;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RideOfferRequest extends FormRequest
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
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'vehicle_id' => [
                $required,
                'integer',
                Rule::exists('vehicles', 'id')
                    ->where('user_id', $this->user()->id)
                    ->where('status', Vehicle::STATUS_ACTIVE)
                    ->whereNull('deleted_at'),
            ],
            'origin_lat' => [$required, 'numeric', 'between:-90,90'],
            'origin_lng' => [$required, 'numeric', 'between:-180,180'],
            'origin_label' => [$required, 'string', 'max:255'],
            'dest_lat' => [$required, 'numeric', 'between:-90,90'],
            'dest_lng' => [$required, 'numeric', 'between:-180,180'],
            'dest_label' => [$required, 'string', 'max:255'],
            'depart_at' => [$required, 'date'],
            'seats_total' => [$required, 'integer', 'between:1,4'],
            'cost_total_poisha' => [$required, 'integer', 'min:1', 'max:10000000'],
            'provider_participates' => ['sometimes', 'boolean'],
            'women_only' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->has('depart_at')) {
                    $departAt = $this->date('depart_at');

                    if ($departAt !== null && $departAt->lt(now()->addMinutes(15))) {
                        $validator->errors()->add('depart_at', 'Departure must be at least 15 minutes from now.');
                    }

                    if ($departAt !== null && $departAt->gt(now()->addDays(7))) {
                        $validator->errors()->add('depart_at', 'Offers can only be made up to 7 days ahead.');
                    }
                }

                if ($this->boolean('women_only') && $this->user()->gender !== 'female') {
                    $validator->errors()->add('women_only', 'Women-only offers can only be created by women.');
                }

                if ($this->has('seats_total') && $this->has('vehicle_id')) {
                    $vehicle = Vehicle::query()->find($this->input('vehicle_id'));
                    if ($vehicle && (int) $this->input('seats_total') > (int) $vehicle->seat_capacity) {
                        $validator->errors()->add('seats_total', 'This vehicle has fewer seats than requested.');
                    }
                }
            },
        ];
    }
}
