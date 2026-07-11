<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Khi tạo mới: hạn không được ở quá khứ.
        // Khi cập nhật: cho phép giữ nguyên hạn cũ (có thể đã quá hạn) khi sửa các trường khác.
        $isCreate = $this->isMethod('post');
        $dueDateRules = $isCreate
            ? 'nullable|date|after_or_equal:today'
            : 'nullable|date';

        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'due_date' => $dueDateRules,
        ];
    }
}
