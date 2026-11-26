<?php

namespace App\Validators\Businesses;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppMessagingSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $keywords = $this->input('opt_out_keywords');
        if (is_array($keywords)) {
            $this->merge(['opt_out_keywords' => array_map(
                static fn (mixed $keyword): mixed => is_string($keyword) ? mb_strtoupper(trim($keyword)) : $keyword,
                $keywords,
            )]);
        }
    }

    public function rules(): array
    {
        return [
            'opt_out_keywords' => ['required', 'array', 'min:1', 'max:20'],
            'opt_out_keywords.*' => ['required', 'string', 'min:2', 'max:40', 'distinct:ignore_case', 'regex:/^[\p{L}\p{N}]+(?:[ _-][\p{L}\p{N}]+)*$/u'],
        ];
    }
}
