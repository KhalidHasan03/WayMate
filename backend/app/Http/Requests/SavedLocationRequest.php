<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavedLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'label' => [$required, 'string', 'max:60'],
            'address' => [$required, 'string', 'max:255'],
            'lat' => [$required, 'numeric', 'between:-90,90'],
            'lng' => [$required, 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
