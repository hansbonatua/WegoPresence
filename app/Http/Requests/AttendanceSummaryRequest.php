<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceSummaryRequest extends FormRequest
{
    /**
     * The maximum number of calendar days allowed per request.
     */
    private const MAX_RANGE_DAYS = 31;

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
        ];
    }

    /**
     * Reject ranges longer than the allowed number of days.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $startDate = CarbonImmutable::parse($this->input('start_date'));
                $endDate = CarbonImmutable::parse($this->input('end_date'));

                if ($startDate->diffInDays($endDate) >= self::MAX_RANGE_DAYS) {
                    $validator->errors()->add(
                        'end_date',
                        'The date range cannot exceed '.self::MAX_RANGE_DAYS.' days.',
                    );
                }
            },
        ];
    }
}
