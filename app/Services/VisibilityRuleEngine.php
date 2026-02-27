<?php

namespace App\Services;

/**
 * VisibilityRuleEngine evaluates dynamic visibility rules for form fields.
 *
 * Supports logical operators: AND, OR, NOT
 * Supports condition operators: ==, !=, <, >, <=, >=, in, not_in, contains, regex, isset, empty
 *
 * Example rule structure:
 * {
 *     "operator": "AND",
 *     "conditions": [
 *         {
 *             "fieldKey": "plant",
 *             "operator": "==",
 *             "value": "P1"
 *         },
 *         {
 *             "fieldKey": "department",
 *             "operator": "in",
 *             "value": ["HR", "IT"]
 *         }
 *     ]
 * }
 */
class VisibilityRuleEngine
{
    /**
     * Evaluate visibility rules for all fields in a form version.
     * Returns array of field_key => visibility_boolean.
     *
     * @param array $fields Collection of FormField models
     * @param array $answers Current form answers (field_key => value)
     * @return array Mapping of field_key => is_visible
     */
    public function evaluateAllFields(array $fields, array $answers): array
    {
        $visibleFields = [];

        foreach ($fields as $field) {
            // If no visibility rule, field is always visible
            if (empty($field->visibility_rule_json)) {
                $visibleFields[$field->field_key] = true;
                continue;
            }

            $rule = $field->visibility_rule_json;
            $isVisible = $this->evaluateRule($rule, $answers);
            $visibleFields[$field->field_key] = $isVisible;
        }

        return $visibleFields;
    }

    /**
     * Evaluate a single visibility rule.
     * Rule uses logical operators (AND, OR, NOT) to combine conditions.
     */
    public function evaluateRule(array $rule, array $answers): bool
    {
        if (empty($rule)) {
            return true; // No rule = always visible
        }

        $operator = $rule['operator'] ?? 'AND';
        $conditions = $rule['conditions'] ?? [];

        if (empty($conditions)) {
            return true; // No conditions = always visible
        }

        $results = [];
        foreach ($conditions as $condition) {
            $results[] = $this->evaluateCondition($condition, $answers);
        }

        // Apply logical operator
        return match ($operator) {
            'AND' => !in_array(false, $results), // All must be true
            'OR' => in_array(true, $results),     // At least one must be true
            'NOT' => !in_array(true, $results),   // All must be false
            default => true,
        };
    }

    /**
     * Evaluate a single condition.
     * Returns true if condition is met, false otherwise.
     */
    public function evaluateCondition(array $condition, array $answers): bool
    {
        $fieldKey = $condition['fieldKey'] ?? null;
        if (empty($fieldKey)) {
            return true; // Invalid condition = pass
        }

        $operator = $condition['operator'] ?? '==';
        $value = $condition['value'] ?? null;
        $answerValue = $answers[$fieldKey] ?? null;

        try {
            return match ($operator) {
                '==' => $this->equalsOperator($answerValue, $value),
                '!=' => !$this->equalsOperator($answerValue, $value),
                '<' => $this->numericCompare($answerValue, $value) < 0,
                '>' => $this->numericCompare($answerValue, $value) > 0,
                '<=' => $this->numericCompare($answerValue, $value) <= 0,
                '>=' => $this->numericCompare($answerValue, $value) >= 0,
                'in' => $this->inOperator($answerValue, (array) $value),
                'not_in' => !$this->inOperator($answerValue, (array) $value),
                'contains' => $this->containsOperator($answerValue, $value),
                'not_contains' => !$this->containsOperator($answerValue, $value),
                'regex' => $this->regexOperator($answerValue, $value),
                'isset' => isset($answers[$fieldKey]),
                'empty' => empty($answers[$fieldKey]),
                'is_true' => $this->isTruthy($answerValue),
                'is_false' => !$this->isTruthy($answerValue),
                default => true,
            };
        } catch (\Exception $e) {
            // Log error but don't break visibility evaluation
            \Illuminate\Support\Facades\Log::warning("Visibility rule error: " . $e->getMessage());
            return true; // Default to visible on error
        }
    }

    /**
     * Equality operator - handle strict type checking.
     */
    private function equalsOperator(mixed $answerValue, mixed $value): bool
    {
        if ($this->isTruthy($value) === null || $this->isTruthy($answerValue) === null) {
            // Handle boolean-like strings
            return \strtolower((string) $answerValue) === \strtolower((string) $value);
        }

        return $answerValue == $value;
    }

    /**
     * In operator - check if value is in array.
     */
    private function inOperator(mixed $answerValue, array $values): bool
    {
        if (is_array($answerValue)) {
            // If answer is array, check if any element is in values
            foreach ($answerValue as $v) {
                if (in_array($v, $values)) {
                    return true;
                }
            }
            return false;
        }

        return in_array($answerValue, $values);
    }

    /**
     * Contains operator - check if string contains substring.
     */
    private function containsOperator(mixed $answerValue, mixed $value): bool
    {
        if ($answerValue === null || $value === null) {
            return false;
        }

        $haystack = (string) $answerValue;
        $needle = (string) $value;

        return str_contains($haystack, $needle);
    }

    /**
     * Regex operator - match pattern.
     */
    private function regexOperator(mixed $answerValue, mixed $pattern): bool
    {
        if ($answerValue === null || empty($pattern)) {
            return false;
        }

        try {
            return \preg_match((string) $pattern, (string) $answerValue) === 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Numeric comparison - convert to numbers and compare.
     */
    private function numericCompare(mixed $a, mixed $b): int
    {
        $numA = is_numeric($a) ? (float) $a : 0;
        $numB = is_numeric($b) ? (float) $b : 0;

        if ($numA < $numB) return -1;
        if ($numA > $numB) return 1;
        return 0;
    }

    /**
     * Check if value is truthy.
     * Handles various truthy representations.
     */
    private function isTruthy(mixed $value): ?bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value)) {
            $lower = \strtolower(\trim($value));
            if (\in_array($lower, ['true', '1', 'yes', 'on'])) {
                return true;
            }
            if (\in_array($lower, ['false', '0', 'no', 'off', ''])) {
                return false;
            }
        }

        return null; // Indeterminate
    }

    /**
     * Get fields that should be visible based on current answers.
     * Useful for progressive disclosure of form fields.
     */
    public function getVisibleFieldKeys(array $fields, array $answers): array
    {
        $visibleFields = $this->evaluateAllFields($fields, $answers);
        return \array_keys(\array_filter($visibleFields));
    }

    /**
     * Get fields that should be hidden based on current answers.
     */
    public function getHiddenFieldKeys(array $fields, array $answers): array
    {
        $visibleFields = $this->evaluateAllFields($fields, $answers);
        return \array_keys(\array_filter($visibleFields, fn($v) => !$v));
    }

    /**
     * Validate rule structure - useful for admin UI.
     */
    public function validateRule(array $rule): array
    {
        $errors = [];

        if (empty($rule['operator'])) {
            $errors[] = 'Missing operator';
        } elseif (!\in_array($rule['operator'], ['AND', 'OR', 'NOT'])) {
            $errors[] = 'Invalid operator: ' . $rule['operator'];
        }

        if (empty($rule['conditions'])) {
            $errors[] = 'Missing conditions';
        } else {
            foreach ($rule['conditions'] as $index => $condition) {
                if (empty($condition['fieldKey'])) {
                    $errors[] = "Condition {$index}: Missing fieldKey";
                }
                if (empty($condition['operator'])) {
                    $errors[] = "Condition {$index}: Missing operator";
                }
            }
        }

        return $errors;
    }
}
