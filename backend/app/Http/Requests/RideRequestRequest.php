<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesDateTimeInput;
use App\Models\RideRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RideRequestRequest extends FormRequest
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
            'origin_lat' => [$required, 'numeric', 'between:-90,90'],
            'origin_lng' => [$required, 'numeric', 'between:-180,180'],
            'origin_label' => [$required, 'string', 'max:255'],
            'dest_lat' => [$required, 'numeric', 'between:-90,90'],
            'dest_lng' => [$required, 'numeric', 'between:-180,180'],
            'dest_label' => [$required, 'string', 'max:255'],
            'depart_at' => [$required, 'date'],
            'seats_needed' => [$required, 'integer', 'between:1,4'],
            'pool_max_seats' => $this->isMethod('post')
                ? ['sometimes', 'integer', 'between:1,4', 'gte:seats_needed']
                : ['sometimes', 'integer', 'between:1,4'],
            'accepting_copassengers' => ['sometimes', 'boolean'],
            'budget_per_seat_poisha' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'women_only' => ['sometimes', 'boolean'],
            'transport_preference' => ['sometimes', Rule::in([
                RideRequest::PREF_ANY,
                RideRequest::PREF_EXTERNAL,
                RideRequest::PREF_PRIVATE,
            ])],
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
                        $validator->errors()->add('depart_at', 'Requests can only be made up to 7 days ahead.');
                    }
                }

                if ($this->boolean('women_only') && $this->user()->gender !== 'female') {
                    $validator->errors()->add('women_only', 'Women-only requests can only be created by women.');
                }

                $accepting = $this->boolean('accepting_copassengers', true);
                if (! $accepting && $this->has('seats_needed') && $this->has('pool_max_seats')) {
                    if ((int) $this->input('seats_needed') !== (int) $this->input('pool_max_seats')) {
                        $validator->errors()->add('pool_max_seats', 'Pool capacity must equal seats needed when co-passengers are not accepted.');
                    }
                }

                if ($this->has('seats_needed') && $this->has('pool_max_seats')) {
                    if ((int) $this->input('seats_needed') > (int) $this->input('pool_max_seats')) {
                        $validator->errors()->add('seats_needed', 'Seats needed cannot exceed pool capacity.');
                    }
                }
            },
        ];
    }
}
