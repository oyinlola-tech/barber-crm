<?php

namespace App\Validators\Analytics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AnalyticsRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $start = $this->input('start_date');
            $end = $this->input('end_date');
            if ($validator->errors()->isNotEmpty() || ! is_string($start) || ! is_string($end)) {
                return;
            }

            if (now()->parse($start)->diffInDays(now()->parse($end)) > 365) {
                $validator->errors()->add('end_date', 'The selected date range cannot exceed 366 days.');
            }
        }];
    }
}
