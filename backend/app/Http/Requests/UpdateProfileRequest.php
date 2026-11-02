<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'gender' => ['sometimes', 'in:male,female,other,undisclosed'],
            'dob' => ['sometimes', 'nullable', 'date', 'before:today'],
            'locale' => ['sometimes', 'in:en,bn'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'preferred_vehicle_type' => ['sometimes', 'in:motorcycle,scooter,car,any'],
            'women_only_opt_in' => ['sometimes', 'boolean'],
            'notify_prefs' => ['sometimes', 'array'],
            'notify_prefs.*' => ['boolean'],
            'accept_terms' => ['sometimes', 'boolean'],
            'accept_cost_share_disclaimer' => ['sometimes', 'boolean'],
        ];
    }
}
