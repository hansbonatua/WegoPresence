<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'destination' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'min:3', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
