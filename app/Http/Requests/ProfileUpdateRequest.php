<?php

namespace App\Http\Requests;

use App\Support\SocialLinks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /** Chuẩn hoá dữ liệu trước khi validate: lowercase username + thêm scheme cho social. */
    protected function prepareForValidation(): void
    {
        if ($this->filled('username')) {
            $this->merge(['username' => mb_strtolower(trim($this->input('username')))]);
        }
        if (is_array($this->social)) {
            $this->merge(['social' => SocialLinks::normalize($this->social)]);
        }
    }

    public function rules(): array
    {
        $id = Auth::id();

        return [
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['required', 'email:rfc,strict', Rule::unique('users', 'email')->ignore($id)],
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:10240',
            'social' => 'nullable|array',
            'social.facebook' => 'nullable|url|max:255',
            'social.twitter' => 'nullable|url|max:255',
            'social.linkedin' => 'nullable|url|max:255',
            'social.github' => 'nullable|url|max:255',
            'social.website' => 'nullable|url|max:255',
            // Đổi mật khẩu là tuỳ chọn; để trống nếu không đổi.
            'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'username.alpha_dash' => 'Tên đăng nhập chỉ gồm chữ, số, gạch ngang và gạch dưới.',
            'username.unique' => 'Tên đăng nhập đã được sử dụng.',
            'avatar.max' => 'Ảnh đại diện tối đa 10MB.',
            'social.*.url' => 'Liên kết mạng xã hội phải là URL hợp lệ.',
        ];
    }
}
