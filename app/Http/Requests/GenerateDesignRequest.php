<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateDesignRequest extends FormRequest
{
    /** Only authenticated users may request an AI design generation. */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** Validation rules for the AI design generation payload. */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'product_id' => ['required', 'exists:products,id'],
            'product_option_id' => ['nullable', 'exists:product_options,id'],
            'prompt' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
