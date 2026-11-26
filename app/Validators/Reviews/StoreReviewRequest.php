<?php

namespace App\Validators\Reviews;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null
            && $this->user()->hasBusinessPermission((int) $businessId, 'reviews.create')
            && $this->user()->hasBusinessPermission((int) $businessId, 'customers.view');
    }

    public function rules(): array
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('business_id', $businessId)],
            'visit_id' => ['nullable', 'integer', Rule::exists('visits', 'id')->where('business_id', $businessId)],
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:5000'],
            'source' => ['sometimes', 'string', 'max:80'],
            'reviewed_at' => ['sometimes', 'date', 'before_or_equal:now'],
        ];
    }
}
