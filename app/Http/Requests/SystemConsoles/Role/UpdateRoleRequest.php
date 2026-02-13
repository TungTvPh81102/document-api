<?php

namespace App\Http\Requests\SystemConsoles\Role;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends BaseFormRequest
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
        $routeParam = $this->route('role');
        $id = is_object($routeParam) ? $routeParam->id : $routeParam;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('roles', 'slug')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_system' => ['sometimes', 'required', 'boolean'],
            'level' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'enabled' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên vai trò không được để trống',
            'name.string' => 'Tên vai trò phải là chuỗi ký tự',
            'name.max' => 'Tên vai trò không được vượt quá 255 ký tự',
            'name.unique' => 'Tên vai trò đã tồn tại',

            'slug.required' => 'Slug không được để trống',
            'slug.string' => 'Slug phải là chuỗi ký tự',
            'slug.max' => 'Slug không được vượt quá 255 ký tự',
            'slug.alpha_dash' => 'Slug chỉ được chứa chữ cái, số, dấu gạch ngang và gạch dưới',
            'slug.unique' => 'Slug đã tồn tại',

            'description.string' => 'Mô tả phải là chuỗi ký tự',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự',

            'is_system.required' => 'Trường is_system là bắt buộc',
            'is_system.boolean' => 'is_system phải là kiểu boolean (true/false)',

            'level.integer' => 'Level phải là số nguyên',
            'level.min' => 'Level không được nhỏ hơn 0',

            'enabled.boolean' => 'Trạng thái kích hoạt phải là kiểu boolean (true/false)',
        ];
    }
}
