<?php

namespace App\Http\Requests\Media;

use App\Media\MediaCollectionConfig;
use App\Services\ModelResolver;
use Illuminate\Foundation\Http\FormRequest;

/**
 * UploadMediaRequest — Validate media upload request
 * 
 * 1 class duy nhất xử lý tất cả models + collections
 * 
 * Routes dynamic check:
 *   - {modelType} là snake_case
 *   - {collection} phải được hỗ trợ bởi model đó
 * 
 * Validate rules lấy từ MediaCollectionConfig
 */
class UploadMediaRequest extends FormRequest
{
    protected ?string $modelType = null;
    protected ?string $collection = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization logic được handle ở controller + policy
        // FormRequest chỉ validate request structure
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $this->modelType = $this->route('modelType');
        $this->collection = $this->route('collection');

        $rules = [
            'file' => 'required|file',
        ];

        // Thêm collection-specific rules nếu collection hợp lệ
        if ($this->isValidCollection()) {
            $collectionRules = MediaCollectionConfig::getValidationRules($this->collection, 'file');
            $rules = array_merge($rules, $collectionRules);
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return MediaCollectionConfig::getValidationMessages('file');
    }

    /**
     * Kiểm tra collection hợp lệ
     */
    protected function isValidCollection(): bool
    {
        if (!$this->collection) {
            return false;
        }

        try {
            $resolver = new ModelResolver();

            // Validate model type
            if (!$resolver->isSupported($this->modelType)) {
                return false;
            }

            // Validate collection for this model
            return $resolver->isValidCollectionForModel($this->modelType, $this->collection);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException(
            $validator,
            response()->json([
                'success' => false,
                'message' => 'File không hợp lệ',
                'errors' => $validator->errors(),
            ], 422),
        );
    }
}
