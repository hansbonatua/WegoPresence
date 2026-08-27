<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecapAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'search' => ['nullable', 'string', 'max:100'],
            'nip' => ['nullable', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:100'],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'attendance_status' => ['nullable', Rule::in(['on_time', 'late'])],
        ];
    }
}
