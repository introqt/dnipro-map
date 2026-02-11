<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'latitude' => ['sometimes', 'numeric', 'between:48.35,48.60'],
            'longitude' => ['sometimes', 'numeric', 'between:34.90,35.15'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'photo_url' => ['nullable', 'url'],
            'type' => ['sometimes', 'in:static_danger,moving_person,danger_road'],
        ];
    }
}
