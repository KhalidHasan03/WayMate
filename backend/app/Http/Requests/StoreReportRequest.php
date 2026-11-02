<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in([
                Report::CATEGORY_SAFETY,
                Report::CATEGORY_HARASSMENT,
                Report::CATEGORY_FRAUD,
                Report::CATEGORY_NO_SHOW,
                Report::CATEGORY_OTHER,
            ])],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'reported_user_id' => ['nullable', 'integer', 'exists:users,id', 'not_in:'.$this->user()->id],
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
        ];
    }
}
