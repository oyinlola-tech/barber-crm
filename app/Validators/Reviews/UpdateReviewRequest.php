<?php

namespace App\Validators\Reviews;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, 'reviews.update') ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
