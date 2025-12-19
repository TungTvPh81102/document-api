<?php

namespace App\Http\Requests;

use App\Traits\ApiResponseTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseFormRequest extends FormRequest
{
    use ApiResponseTrait;

    /**
     * Messages for validation errors
     */
    protected array $customMessages = [];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    abstract public function rules(): array;

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return $this->customMessages;
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();

        app(\App\Services\LoggerService::class)->logApiError(
            new \Exception('Dữ liệu không hợp lệ'),
            $this
        );

        $response = $this->errorResponse(
            'Dữ liệu không hợp lệ',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $errors->messages()
        );

        throw new HttpResponseException($response);
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        $response = $this->errorResponse(
            'Bạn không có quyền thực hiện hành động này',
            Response::HTTP_FORBIDDEN
        );

        throw new HttpResponseException($response);
    }

    /**
     * Get data after validation
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated();

        // Trim string values
        foreach ($validated as &$value) {
            if (is_string($value)) {
                $value = trim($value);
            }
        }

        return $key ? ($validated[$key] ?? $default) : $validated;
    }

    /**
     * Convenience method to check if user is authenticated
     */
    protected function isAuthenticated(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get authenticated user ID
     */
    protected function userId(): ?int
    {
        return $this->user()?->id;
    }
}
