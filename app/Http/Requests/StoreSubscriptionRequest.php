<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:48.35,48.60'],
            'longitude' => ['required', 'numeric', 'between:34.90,35.15'],
            'radius_km' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}
