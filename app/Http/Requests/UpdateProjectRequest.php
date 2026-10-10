<?php

namespace App\Http\Requests;

class UpdateProjectRequest extends StoreProjectRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }
}
