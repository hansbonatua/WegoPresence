<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceComplaintRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $attendance = Attendance::query()
            ->whereKey($this->input('attendance_id'))
            ->first();

        return $attendance !== null
            && $attendance->user_id === $this->user()?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'attendance_id' => ['required', 'integer', 'exists:attendances,id'],
            'complaint_reason' => ['required', 'string', 'max:5000'],
        ];
    }
}
