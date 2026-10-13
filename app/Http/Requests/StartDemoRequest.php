<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartDemoRequest extends FormRequest
{
    /**
     * Send validation failures back to the form that holds the checkbox.
     */
    protected $redirectRoute = 'demo.create';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'terms' => ['accepted'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms.accepted' => 'Please agree to the Terms to create a demo workspace.',
        ];
    }
}
