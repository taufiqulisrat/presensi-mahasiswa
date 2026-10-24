<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassroomRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('classrooms', 'code')->ignore($this->route('classroom'))],
            'name' => ['required', 'string', 'max:255'],
            'academic_year' => ['required', 'regex:/^\\d{4}\/\\d{4}$/'],
            'semester' => ['required', 'integer', 'between:1,14'],
        ];
    }
}
