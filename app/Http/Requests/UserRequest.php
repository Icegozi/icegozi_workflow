<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
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
        $userId = $this->route('id');

        return [
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|min:3|max:50|alpha_dash|unique:users,username,' . $userId,
            'email' => 'required|email:rfc,strict|unique:users,email,' . $userId,
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
            'social' => 'nullable|array',
            'social.facebook' => 'nullable|url|max:255',
            'social.twitter' => 'nullable|url|max:255',
            'social.linkedin' => 'nullable|url|max:255',
            'social.github' => 'nullable|url|max:255',
            'social.website' => 'nullable|url|max:255',
            'password' => [
                'nullable',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/',
                'confirmed',
            ],
            'status' => 'required|in:active,inactive,banned',
            'is_admin' => 'nullable|boolean',
        ];
    }
}
