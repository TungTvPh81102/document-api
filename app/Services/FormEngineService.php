<?php

namespace App\Services;

use App\Models\DynamicService\FormField;
use App\Models\DynamicService\FormTemplateVersion;
use Illuminate\Support\Str;

class FormEngineService
{
    /**
     * Standardize an upstream payload to the canonical internal JSON contract.
     * Supports multiple payload structures with flexible path resolution.
     */
    public function standardizePayload(array $rawPayload): array
    {
        $result = [
            'service' => [
                'svcCode' => $this->resolvePath($rawPayload, [
                    'resultBody.serviceNameList.0.svcId',
                    'serviceNameList.0.svcId',
                    'svcId',
                    'service_code',
                    'code'
                ]) ?? 'UNKNOWN',
                'titles'  => [],
            ],
            'form' => [
                'fields' => [],
            ],
        ];

        // Map titles from multiple possible paths
        $titleList = $this->getNestedValue($rawPayload, 'resultBody.serviceNameList', null)
            ?? $this->getNestedValue($rawPayload, 'serviceNameList', null)
            ?? [];

        foreach ($titleList as $titleItem) {
            $locale = $this->normalizeLocale($titleItem['lan'] ?? $titleItem['locale'] ?? 'en');
            if ($this->isLocaleSupported($locale)) {
                $result['service']['titles'][] = [
                    'locale' => $locale,
                    'title'  => $titleItem['svcTitle'] ?? $titleItem['title'] ?? '',
                ];
            }
        }

        // Map fields from multiple possible paths
        $fields = $this->getNestedValue($rawPayload, 'resultBody.svcQuesDetail', null)
            ?? $this->getNestedValue($rawPayload, 'svcQuesDetail', null)
            ?? $this->getNestedValue($rawPayload, 'fields', null)
            ?? [];

        foreach ($fields as $rawField) {
            $result['form']['fields'][] = $this->mapFieldToCanonical($rawField);
        }

        return $result;
    }

    /**
     * Map a raw field definition to our internal canonical structure.
     */
    private function mapFieldToCanonical(array $rawField): array
    {
        // Support both question_type and questiontype
        $typeId = $rawField['question_type'] ?? $rawField['questiontype'] ?? $rawField['typeid'] ?? 1;
        
        // Support both question_title and questiontitle
        $title = $rawField['question_title'] ?? $rawField['questiontitle'] ?? 'Unnamed Field';
        
        // Normalize options
        $options = $rawField['question_option'] ?? $rawField['questionoption'] ?? [];
        if (is_string($options)) {
            if (empty($options)) {
                $options = [];
            } elseif (str_contains($options, ';')) {
                $options = explode(';', $options);
            } elseif (str_contains($options, '|')) {
                $options = explode('|', $options);
            } else {
                $options = [$options];
            }
        }

        return [
            'fieldKey'   => (string) ($rawField['question_id'] ?? $rawField['questionid'] ?? Str::uuid()),
            'fieldType'  => $this->mapTypeIdToName($typeId),
            'required'   => ($rawField['allow_null'] ?? $rawField['allownull'] ?? 'Y') === 'N',
            'options'    => $options,
            'labels'     => [
                'default' => $title,
            ],
            'validation' => [], // To be extended
        ];
    }

    /**
     * Normalize locale strings (e.g. zhtw -> zh_tw).
     */
    private function normalizeLocale(string $locale): string
    {
        $map = [
            'zhtw'  => 'zh_tw',
            'zh_tw' => 'zh_tw',
            'zh'    => 'zh',
            'en'    => 'en',
            'vi'    => 'vi',
            'vn'    => 'vi',
        ];

        return $map[strtolower($locale)] ?? $locale;
    }

    /**
     * Map numeric type IDs to descriptive names.
     */
    private function mapTypeIdToName(int|string $typeId): string
    {
        $types = [
            1  => 'single_choice',
            2  => 'multi_choice',
            3  => 'short_text',
            4  => 'long_text',
            5  => 'file',
            9  => 'short_text', // Based on "Hộp văn bản dòng đơn"
            10 => 'employee_code',
            11 => 'plant_code',
        ];

        return $types[$typeId] ?? 'short_text';
    }

    /**
     * Validate a submission against a form version.
     * Comprehensive validation with type-aware checks.
     */
    public function validateSubmission(FormTemplateVersion $version, array $answers): array
    {
        $errors = [];
        $fields = $version->fields()->get();

        foreach ($fields as $field) {
            $value = $answers[$field->field_key] ?? null;

            // Required check
            if ($field->is_required && empty($value)) {
                $errors[$field->field_key] = "Field '{$field->label_default}' is required.";
                continue;
            }

            // Type-specific validation
            if ($value !== null && $value !== '') {
                $fieldErrors = $this->validateFieldValue($field, $value);
                if (!empty($fieldErrors)) {
                    $errors[$field->field_key] = $fieldErrors[0];
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a single field value with type-aware rules.
     */
    private function validateFieldValue(FormField $field, mixed $value): array
    {
        $errors = [];
        $validation = $field->validation_json ?? [];

        switch ($field->field_type) {
            case 'short_text':
            case 'long_text':
            case 'textarea':
                $errors = $this->validateTextField($value, $validation);
                break;

            case 'date':
                $errors = $this->validateDateField($value, $validation);
                break;

            case 'datetime':
                $errors = $this->validateDateTimeField($value, $validation);
                break;

            case 'numeric':
                $errors = $this->validateNumericField($value, $validation);
                break;

            case 'email':
                $errors = $this->validateEmailField($value);
                break;

            case 'phone':
                $errors = $this->validatePhoneField($value);
                break;

            case 'url':
                $errors = $this->validateUrlField($value);
                break;

            case 'single_choice':
            case 'plant_code':
            case 'employee_code':
                $errors = $this->validateChoiceField($value, $field->options_json ?? []);
                break;

            case 'multi_choice':
                $errors = $this->validateMultiChoiceField($value, $field->options_json ?? [], $validation);
                break;

            case 'file':
                // File validation done in ServiceRequestService
                break;
        }

        return $errors;
    }

    /**
     * Validate text fields (min/max length, regex pattern).
     */
    private function validateTextField(mixed $value, array $validation): array
    {
        $errors = [];

        if (!is_string($value)) {
            $errors[] = 'Value must be a string';
            return $errors;
        }

        $length = strlen($value);
        $minLength = $validation['minLength'] ?? 1;
        $maxLength = $validation['maxLength'] ?? 4000;

        if ($length < $minLength) {
            $errors[] = "Minimum {$minLength} characters required";
        } elseif ($length > $maxLength) {
            $errors[] = "Maximum {$maxLength} characters allowed";
        }

        if (!empty($validation['pattern'])) {
            if (!preg_match($validation['pattern'], $value)) {
                $errors[] = "Value does not match required pattern";
            }
        }

        return $errors;
    }

    /**
     * Validate date fields (format, min/max date).
     */
    private function validateDateField(mixed $value, array $validation): array
    {
        $errors = [];
        $format = $validation['format'] ?? 'Y-m-d';

        if (!$this->isValidDate($value, $format)) {
            $errors[] = "Invalid date format. Expected: {$format}";
            return $errors;
        }

        // Validate date range if specified
        if (!empty($validation['minDate'])) {
            if (strtotime($value) < strtotime($validation['minDate'])) {
                $errors[] = "Date must be on or after {$validation['minDate']}";
            }
        }

        if (!empty($validation['maxDate'])) {
            if (strtotime($value) > strtotime($validation['maxDate'])) {
                $errors[] = "Date must be on or before {$validation['maxDate']}";
            }
        }

        return $errors;
    }

    /**
     * Validate datetime fields.
     */
    private function validateDateTimeField(mixed $value, array $validation): array
    {
        $errors = [];

        try {
            new \DateTime($value);
        } catch (\Exception $e) {
            $errors[] = "Invalid datetime format";
        }

        return $errors;
    }

    /**
     * Validate numeric fields (min/max, decimal places).
     */
    private function validateNumericField(mixed $value, array $validation): array
    {
        $errors = [];

        if (!is_numeric($value)) {
            $errors[] = 'Value must be numeric';
            return $errors;
        }

        $numValue = (float) $value;

        if ($validation['min'] !== null && $numValue < $validation['min']) {
            $errors[] = "Value must be >= {$validation['min']}";
        }

        if ($validation['max'] !== null && $numValue > $validation['max']) {
            $errors[] = "Value must be <= {$validation['max']}";
        }

        // Check decimal places if required
        if ($validation['decimal'] === false && strpos((string)$value, '.') !== false) {
            $errors[] = "Decimal values not allowed";
        }

        return $errors;
    }

    /**
     * Validate email fields.
     */
    private function validateEmailField(mixed $value): array
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ["Invalid email format"];
        }
        return [];
    }

    /**
     * Validate phone fields.
     */
    private function validatePhoneField(mixed $value): array
    {
        // Basic phone validation - can be extended
        if (!preg_match('/^[+]?[(]?[0-9]{3}[)]?[-\s.]?[0-9]{3}[-\s.]?[0-9]{4,6}$/m', (string)$value)) {
            return ["Invalid phone number format"];
        }
        return [];
    }

    /**
     * Validate URL fields.
     */
    private function validateUrlField(mixed $value): array
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return ["Invalid URL format"];
        }
        return [];
    }

    /**
     * Validate single choice field.
     */
    private function validateChoiceField(mixed $value, array $options): array
    {
        if (!in_array($value, $options)) {
            return ["Invalid option. Valid options: " . implode(', ', $options)];
        }
        return [];
    }

    /**
     * Validate multi-choice field.
     */
    private function validateMultiChoiceField(mixed $value, array $options, array $validation): array
    {
        $values = (array) $value;
        $errors = [];

        $minSelection = $validation['minSelection'] ?? 1;
        $maxSelection = $validation['maxSelection'] ?? count($options);

        if (count($values) < $minSelection) {
            $errors[] = "Select at least {$minSelection} option(s)";
        }

        if (count($values) > $maxSelection) {
            $errors[] = "Select maximum {$maxSelection} option(s)";
        }

        foreach ($values as $v) {
            if (!in_array($v, $options)) {
                $errors[] = "Invalid option: {$v}";
                break;
            }
        }

        return $errors;
    }

    /**
     * Check if date is valid in given format.
     */
    private function isValidDate(mixed $value, string $format = 'Y-m-d'): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $d = \DateTime::createFromFormat($format, $value);
        return $d !== false && $d->format($format) === $value;
    }

    /**
     * Resolve nested path with fallbacks.
     * Example: 'resultBody.serviceNameList.0.svcId'
     */
    private function resolvePath(array $data, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = $this->getNestedValue($data, $path);
            if ($value !== null) {
                return (string) $value;
            }
        }
        return null;
    }

    /**
     * Get nested value from array using dot notation.
     * Supports array indices like 'items.0.name'
     */
    private function getNestedValue(array $data, string $path, mixed $default = null): mixed
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && (isset($value[$key]) || array_key_exists($key, $value))) {
                $value = $value[$key];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Check if locale is supported.
     */
    private function isLocaleSupported(string $locale): bool
    {
        $supported = ['zh', 'zh_tw', 'en', 'vi'];
        return in_array($this->normalizeLocale($locale), $supported);
    }
