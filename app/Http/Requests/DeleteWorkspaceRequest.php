<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteWorkspaceRequest extends FormRequest
{
    /**
     * Only a signed-in user can delete, and only their own workspace.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Prepare the confirmation input for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('confirmation'))) {
            $this->merge(['confirmation' => trim($this->input('confirmation'))]);
        }
    }

    /**
     * The typed Demo ID is only a confirmation. It never selects which workspace is deleted.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! hash_equals((string) $this->user()->demo_id, (string) $value)) {
                    $fail('The Demo ID you typed does not match this workspace.');
                }
            }],
        ];
    }
}
