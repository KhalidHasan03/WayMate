<?php

namespace App\Http\Requests;

use App\Models\VerificationDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerificationDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                VerificationDocument::TYPE_NID,
                VerificationDocument::TYPE_LICENSE,
                VerificationDocument::TYPE_SELFIE,
            ])],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'number' => ['nullable', 'string', 'max:40'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}
