<?php

namespace App\Http\Requests\Systems;

use App\Http\Requests\BaseFormRequest;

class StoreUserRequest extends BaseFormRequest
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
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'avatar' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'enabled' => 'nullable|boolean',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {

        return [
            'name.required' => 'Tên người dùng không được để trống',
            'name.string' => 'Tên người dùng phải là chuỗi ký tự',
            'name.max' => 'Tên người dùng không được vượt quá 255 ký tự',

            'email.required' => 'Email không được để trống',
            'email.email' => 'Email không hợp lệ',
            'email.unique' => 'Email đã được sử dụng',
            'email.max' => 'Email không được vượt quá 255 ký tự',

            'password.required' => 'Mật khẩu không được để trống',
            'password.string' => 'Mật khẩu phải là chuỗi ký tự',
            'password.min' => 'Mật khẩu phải ít nhất 8 ký tự',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp',

            'password_confirmation.required' => 'Vui lòng nhập xác nhận mật khẩu',
            'password_confirmation.string' => 'Xác nhận mật khẩu phải là chuỗi ký tự',
            'password_confirmation.min' => 'Xác nhận mật khẩu phải ít nhất 8 ký tự',

            'phone.string' => 'Số điện thoại phải là chuỗi ký tự',
            'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự',
            'phone.unique' => 'Số điện thoại đã được sử dụng',

            'date_of_birth.date' => 'Ngày sinh phải là định dạng ngày hợp lệ (YYYY-MM-DD)',

            'gender.in' => 'Giới tính phải là một trong: male, female, other',

            'avatar.file' => 'Tệp ảnh đại diện phải là một tệp hợp lệ',
            'avatar.image' => 'Ảnh đại diện phải là tệp hình ảnh',
            'avatar.mimes' => 'Ảnh đại diện chỉ chấp nhận các định dạng: jpeg, png, jpg, gif, svg',
            'avatar.max' => 'Ảnh đại diện vượt quá dung lượng cho phép (tối đa 2MB)',

            'enabled.boolean' => 'Trạng thái kích hoạt phải là kiểu boolean (true/false)',
        ];
    }
}
