# Dynamic Service Form - Optimization Guide

## Executive Summary

Current implementation has a solid foundation with proper DB schema, versioning, and multi-language support. However, there are critical gaps in **validation framework**, **file handling**, and **flexibility** that need addressing.

---

## 1. FORMENGINESERVICE OPTIMIZATIONS

### Issue 1.1: Hardcoded Payload Structure

**Current:** Deeply nested hardcoded paths: `$rawPayload['resultBody']['serviceNameList'][0]['svcId']`
**Risk:** Breaks if upstream format changes with no fallback
**Solution:** Use flexible path resolver with multiple fallback paths

```php
// NEW: Add flexible path resolver
private function resolvePath(array $data, array $paths): ?string {
    foreach ($paths as $path) {
        $value = $this->getNestedValue($data, $path);
        if ($value !== null) return $value;
    }
    return null;
}

// Usage in standardizePayload:
$svcCode = $this->resolvePath($rawPayload, [
    'resultBody.serviceNameList.0.svcId',
    'serviceNameList.0.svcId',
    'svcId',
    'service_code',
    'code'
]);
```

### Issue 1.2: Insufficient Validation Rules

**Current:** Only checks required fields + single_choice options
**Risk:** Invalid data persists (empty text fields, invalid dates, regex mismatch)
**Solution:** Expand validation rules

```php
// NEW: Comprehensive field validation
private array $validationRules = [
    'short_text' => ['minLength' => 1, 'maxLength' => 200, 'pattern' => null],
    'long_text' => ['minLength' => 1, 'maxLength' => 4000, 'pattern' => null],
    'single_choice' => ['required' => true],
    'multi_choice' => ['minSelection' => 1, 'maxSelection' => null],
    'date' => ['format' => 'Y-m-d', 'minDate' => null, 'maxDate' => null],
    'numeric' => ['min' => null, 'max' => null, 'decimal' => false],
    'email' => ['pattern' => 'email'],
    'file' => ['maxSize' => 5242880, 'allowedMimes' => []],
    'employee_code' => ['pattern' => '^[A-Z0-9]{6,10}$'],
];

public function validateSubmission(FormTemplateVersion $version, array $answers): array {
    $errors = [];
    $fields = $version->fields()->get();

    foreach ($fields as $field) {
        $value = $answers[$field->field_key] ?? null;

        // Required check
        if ($field->is_required && empty($value)) {
            $errors[$field->field_key] = "Field '{$field->label_default}' is required.";
            continue;
        }

        if ($value) {
            $fieldErrors = $this->validateFieldValue($field, $value);
            if (!empty($fieldErrors)) {
                $errors[$field->field_key] = $fieldErrors[0];
            }
        }
    }

    return $errors;
}

private function validateFieldValue(FormField $field, mixed $value): array {
    $errors = [];
    $validation = $field->validation_json ?? [];

    switch ($field->field_type) {
        case 'short_text':
        case 'long_text':
            if (!is_string($value)) {
                $errors[] = 'Value must be string';
            } elseif (strlen($value) < ($validation['minLength'] ?? 1)) {
                $errors[] = "Minimum length is {$validation['minLength']}";
            } elseif (strlen($value) > ($validation['maxLength'] ?? 4000)) {
                $errors[] = "Maximum length is {$validation['maxLength']}";
            } elseif (!empty($validation['pattern']) && !preg_match($validation['pattern'], $value)) {
                $errors[] = "Value does not match required pattern";
            }
            break;

        case 'date':
            if (!$this->isValidDate($value, $validation['format'] ?? 'Y-m-d')) {
                $errors[] = "Invalid date format. Expected: {$validation['format']}";
            }
            break;

        case 'numeric':
            if (!is_numeric($value)) {
                $errors[] = 'Value must be numeric';
            } elseif ($validation['min'] !== null && $value < $validation['min']) {
                $errors[] = "Value must be >= {$validation['min']}";
            } elseif ($validation['max'] !== null && $value > $validation['max']) {
                $errors[] = "Value must be <= {$validation['max']}";
            }
            break;

        case 'email':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }
            break;

        case 'single_choice':
        case 'multi_choice':
            $validOptions = $field->options_json ?? [];
            if ($field->field_type === 'single_choice') {
                if (!in_array($value, $validOptions)) {
                    $errors[] = "Invalid option. Valid options: " . implode(', ', $validOptions);
                }
            } else {
                $values = (array) $value;
                foreach ($values as $v) {
                    if (!in_array($v, $validOptions)) {
                        $errors[] = "Invalid option: $v";
                    }
                }
            }
            break;

        case 'file':
            // File validation handled in ServiceRequestService
            // Here just basic check
            if (empty($value)) {
                $errors[] = 'File is required';
            }
            break;
    }

    return $errors;
}

private function isValidDate(string $value, string $format): bool {
    $d = \DateTime::createFromFormat($format, $value);
    return $d !== false && $d->format($format) === $value;
}
```

### Issue 1.3: Missing Field Type Support

**Current:** Only 11 types mapped, no date/datetime/numeric/email
**Solution:** Extend type mapping

```php
// UPDATED: Comprehensive field type mapping
private function mapTypeIdToName(int|string $typeId): string {
    $types = [
        1  => 'single_choice',
        2  => 'multi_choice',
        3  => 'short_text',
        4  => 'long_text',
        5  => 'file',
        6  => 'date',
        7  => 'datetime',
        8  => 'numeric',
        9  => 'short_text',
        10 => 'employee_code',
        11 => 'plant_code',
        12 => 'email',
        13 => 'phone',
        14 => 'url',
        15 => 'textarea',
    ];

    // Support string type names directly
    if (isset($types[$typeId])) {
        return $types[$typeId];
    }

    // Support string names
    $validated = ['single_choice', 'multi_choice', 'short_text', 'long_text', 'file',
                  'date', 'datetime', 'numeric', 'employee_code', 'plant_code',
                  'email', 'phone', 'url', 'textarea', 'multi_select'];

    return in_array($typeId, $validated) ? $typeId : 'short_text';
}
```

### Issue 1.4: Locale Management

**Current:** Hardcoded locale map in FormEngineService
**Solution:** Make it database-driven but keep backward compatibility

```php
// NEW: ConfigService for locales
class LocaleConfigService {
    private array $supportedLocales = ['zh', 'zh_tw', 'en', 'vi'];
    private array $localeAliases = [
        'zhtw' => 'zh_tw',
        'zh_tw' => 'zh_tw',
        'vn' => 'vi',
        'en_US' => 'en',
        'en_us' => 'en',
    ];

    public function normalize(string $locale): string {
        $lower = strtolower($locale);
        return $this->localeAliases[$lower] ?? $locale;
    }

    public function isSupported(string $locale): bool {
        return in_array($this->normalize($locale), $this->supportedLocales);
    }

    public function getSupported(): array {
        return $this->supportedLocales;
    }
}
```

---

## 2. SERVICEREQUESTSERVICE OPTIMIZATIONS

### Issue 2.1: Placeholder File Handling

**Current:** No actual file processing, just metadata storage
**Solution:** Implement real file upload handling

```php
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

public function submitRequest(Service $service, array $data, string $requesterId): ServiceRequest {
    $version = $service->currentVersion;
    if (!$version) {
        throw new \Exception("Service has no published form version.");
    }

    $errors = $this->formEngine->validateSubmission($version, $data['answers'] ?? []);
    if (!empty($errors)) {
        throw new \Illuminate\Validation\ValidationException(
            null,
            new \Illuminate\Support\MessageBag($errors)
        );
    }

    return DB::transaction(function () use ($service, $version, $data, $requesterId) {
        $request = ServiceRequest::query()->create([
            'request_no'      => $this->generateRequestNumber($service),
            'service_id'      => $service->id,
            'form_version_id' => $version->id,
            'requester_id'    => $requesterId,
            'status'          => 'submitted',
            'submitted_at'    => now(),
        ]);

        // Save Answers
        foreach ($data['answers'] ?? [] as $fieldKey => $value) {
            $field = $version->fields()->where('field_key', $fieldKey)->first();
            if (!$field) continue;
            $this->saveAnswer($request, $field, $value);
        }

        // Handle Attachments with REAL file processing
        foreach ($data['attachments'] ?? [] as $fieldKey => $files) {
            $field = $version->fields()->where('field_key', $fieldKey)->first();
            if (!$field) continue;

            // Support both single file and array of files
            $fileList = is_array($files) && isset($files[0]) && $files[0] instanceof UploadedFile
                ? $files
                : ($files instanceof UploadedFile ? [$files] : []);

            foreach ($fileList as $file) {
                $this->processAttachment($request, $field, $file, $requesterId);
            }
        }

        return $request->load(['answers.field', 'attachments', 'service']);
    });
}

// NEW: Request number generation with timestamp to prevent collisions
private function generateRequestNumber(Service $service): string {
    $timestamp = now()->format('YmdHis');
    $serviceSuffix = substr($service->svc_code, -3);
    $random = strtoupper(Str::random(4));
    $requestNo = "REQ-{$timestamp}-{$serviceSuffix}-{$random}";

    // Ensure uniqueness
    while (ServiceRequest::where('request_no', $requestNo)->exists()) {
        $random = strtoupper(Str::random(4));
        $requestNo = "REQ-{$timestamp}-{$serviceSuffix}-{$random}";
    }

    return $requestNo;
}

// NEW: Real file processing
private function processAttachment(
    ServiceRequest $request,
    $field,
    UploadedFile $file,
    string $uploadedBy
): void {
    // Validate file
    $validation = $field->validation_json ?? [];
    if (!$this->validateFile($file, $validation)) {
        throw new \Exception("File validation failed for field {$field->field_key}");
    }

    // Store file in disk
    $path = $file->storeAs(
        "service-requests/{$request->id}",
        $file->hashName(),
        'uploads' // disk name in filesystems.php
    );

    // Create attachment record
    $request->attachments()->create([
        'field_id'    => $field->id,
        'file_name'   => $file->getClientOriginalName(),
        'file_path'   => $path,
        'mime_type'   => $file->getMimeType(),
        'file_size'   => $file->getSize(),
        'uploaded_by' => $uploadedBy,
    ]);
}

// NEW: File validation
private function validateFile(UploadedFile $file, array $validation): bool {
    $maxSize = $validation['maxSize'] ?? 5242880; // 5MB default
    $allowedMimes = $validation['allowedMimes'] ?? [];

    if ($file->getSize() > $maxSize) {
        throw new \Exception("File exceeds maximum size of " . ($maxSize / 1024 / 1024) . "MB");
    }

    if (!empty($allowedMimes) && !in_array($file->getMimeType(), $allowedMimes)) {
        throw new \Exception("File type not allowed. Allowed types: " . implode(', ', $allowedMimes));
    }

    return true;
}

// UPDATED: More robust answer saving with type validation
private function saveAnswer(ServiceRequest $request, $field, $value): void {
    $answerData = [
        'request_id' => $request->id,
        'field_id'   => $field->id,
    ];

    switch ($field->field_type) {
        case 'short_text':
        case 'long_text':
        case 'employee_code':
        case 'plant_code':
        case 'email':
        case 'phone':
        case 'url':
        case 'textarea':
            $answerData['value_text'] = (string) $value;
            break;

        case 'multi_choice':
            $answerData['value_json'] = is_array($value) ? $value : [$value];
            break;

        case 'date':
            // Parse date format from validation
            $validation = $field->validation_json ?? [];
            $format = $validation['format'] ?? 'Y-m-d';
            try {
                $date = \DateTime::createFromFormat($format, $value);
                $answerData['value_date'] = $date->format('Y-m-d');
            } catch (\Exception $e) {
                throw new \Exception("Invalid date format for field {$field->field_key}");
            }
            break;

        case 'datetime':
            try {
                $dateTime = new \DateTime($value);
                $answerData['value_text'] = $dateTime->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                throw new \Exception("Invalid datetime format for field {$field->field_key}");
            }
            break;

        case 'numeric':
            if (!is_numeric($value)) {
                throw new \Exception("Invalid numeric value for field {$field->field_key}");
            }
            $answerData['value_number'] = (float) $value;
            break;

        case 'single_choice':
            $answerData['value_text'] = (string) $value;
            break;

        default:
            if (is_array($value) || is_object($value)) {
                $answerData['value_json'] = $value;
            } else {
                $answerData['value_text'] = (string) $value;
            }
    }

    ServiceRequestAnswer::query()->create($answerData);
}
```

---

## 3. DATABASE OPTIMIZATIONS

### Issue 3.1: Missing Indexes for Performance

**Solution:** Add indexes for common queries

```php
// NEW Migration: Add missing indexes
Schema::table('services', function (Blueprint $table) {
    $table->index('status'); // for filtering active services
    $table->index('business_category_id');
    $table->index('form_template_id');
    $table->index('current_form_version_id');
});

Schema::table('service_requests', function (Blueprint $table) {
    $table->index(['service_id', 'status']); // for query: where service_id & status
    $table->index('requester_id'); // for user's requests
    $table->index('submitted_at'); // for date range filtering
    $table->index('status'); // for status filtering
});

Schema::table('form_template_versions', function (Blueprint $table) {
    $table->index('status'); // for filter draft/published
    $table->index(['form_template_id', 'version_no']);
});

Schema::table('form_fields', function (Blueprint $table) {
    $table->index(['form_version_id', 'field_key']);
    $table->unique(['form_version_id', 'field_key']); // prevent duplicates per version
});

Schema::table('service_request_answers', function (Blueprint $table) {
    $table->index('field_id');
});
```

---

## 4. VISIBILITY RULES ENGINE

### Issue 4.1: Visibility Rules Never Evaluated

**Solution:** Implement rule evaluation

```php
// NEW: VisibilityRuleEngine
class VisibilityRuleEngine {
    public function evaluateRules(
        FormTemplateVersion $version,
        array $answers
    ): array {
        $fields = $version->fields()->get();
        $visibleFields = [];

        foreach ($fields as $field) {
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

    private function evaluateRule(array $rule, array $answers): bool {
        $operator = $rule['operator'] ?? 'AND'; // AND, OR, NOT
        $conditions = $rule['conditions'] ?? [];

        $results = [];
        foreach ($conditions as $condition) {
            $results[] = $this->evaluateCondition($condition, $answers);
        }

        if (empty($results)) return true;

        return match($operator) {
            'AND' => !in_array(false, $results),
            'OR' => in_array(true, $results),
            'NOT' => !in_array(true, $results),
            default => true,
        };
    }

    private function evaluateCondition(array $condition, array $answers): bool {
        $fieldKey = $condition['fieldKey'] ?? null;
        $operator = $condition['operator'] ?? '=='; // ==, !=, <, >, <=, >=, in, not_in, contains, regex
        $value = $condition['value'] ?? null;
        $answerValue = $answers[$fieldKey] ?? null;

        return match($operator) {
            '==' => $answerValue == $value,
            '!=' => $answerValue != $value,
            '<' => $answerValue < $value,
            '>' => $answerValue > $value,
            '<=' => $answerValue <= $value,
            '>=' => $answerValue >= $value,
            'in' => in_array($answerValue, (array) $value),
            'not_in' => !in_array($answerValue, (array) $value),
            'contains' => strpos((string)$answerValue, (string)$value) !== false,
            'regex' => preg_match($value, (string)$answerValue),
            default => true,
        };
    }
}
```

---

## 5. API ERROR CODES

### Issue 5.1: Inconsistent Error Handling

**Solution:** Centralized error codes

```php
// NEW: ErrorCodes Enum
enum ErrorCode: string {
    // Validation errors
    case VALIDATION_ERROR = 'VALIDATION_001';
    case FIELD_REQUIRED = 'FIELD_001';
    case INVALID_TYPE = 'TYPE_001';
    case INVALID_DATE = 'DATE_001';
    case FILE_TOO_LARGE = 'FILE_001';
    case INVALID_MIME = 'FILE_002';

    // Resource not found
    case SERVICE_NOT_FOUND = 'SERVICE_001';
    case REQUEST_NOT_FOUND = 'REQUEST_001';
    case CATEGORY_NOT_FOUND = 'CATEGORY_001';

    // Business logic
    case NO_ACTIVE_VERSION = 'VERSION_001';
    case INVALID_STATUS = 'STATUS_001';
}

// NEW: Structured error response
class ApiError {
    public function __construct(
        public ErrorCode $code,
        public string $message,
        public ?string $field = null,
        public ?.array $details = null,
    ) {}

    public function toArray(): array {
        return [
            'code' => $this->code->value,
            'message' => $this->message,
            'field' => $this->field,
            'details' => $this->details,
        ];
    }
}
```

---

## 6. REQUEST STATUS WORKFLOW

### Current Issue: Only GET/POST, no status transitions

**Solution:** Add status workflow

```php
// NEW: Request Status Transitions
class ServiceRequestStatusTransition {
    public static function canTransition(string $from, string $to): bool {
        $allowed = [
            'submitted' => ['in_progress', 'rejected'],
            'in_progress' => ['approved', 'rejected'],
            'approved' => ['closed'],
            'rejected' => ['submitted'],
            'closed' => [],
        ];

        return in_array($to, $allowed[$from] ?? []);
    }

    public static function getAllowedTransitions(string $status): array {
        return match($status) {
            'submitted' => ['in_progress', 'rejected'],
            'in_progress' => ['approved', 'rejected'],
            'approved' => ['closed'],
            'rejected' => ['submitted'],
            'closed' => [],
            default => [],
        };
    }
}

// NEW Endpoint: Update request status
// PATCH /api/service-requests/{requestNo}/status
public function updateStatus(string $requestNo, Request $request): JsonResponse {
    $validated = $request->validate([
        'status' => 'required|in:in_progress,approved,rejected,closed',
        'notes' => 'nullable|string',
    ]);

    $serviceRequest = ServiceRequest::where('request_no', $requestNo)->first();
    if (!$serviceRequest) {
        return $this->notFoundResponse('Request not found');
    }

    if (!ServiceRequestStatusTransition::canTransition($serviceRequest->status, $validated['status'])) {
        return $this->errorResponse(
            "Cannot transition from {$serviceRequest->status} to {$validated['status']}",
            409
        );
    }

    $serviceRequest->update([
        'status' => $validated['status'],
        'updated_at' => now(),
    ]);

    // Record status change in audit log
    // ...

    return $this->successResponse(new ServiceRequestResource($serviceRequest));
}
```

---

## 7. REQUEST HISTORY / AUDIT LOG

### Issue: No history of changes

**Solution:** Add audit trail

```php
// NEW Table: service_request_history
Schema::create('service_request_history', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained('service_requests')->onDelete('cascade');
    $table->string('action'); // created, status_changed, answer_updated, file_added
    $table->string('old_value')->nullable();
    $table->string('new_value')->nullable();
    $table->string('changed_by');
    $table->timestamps();
});

// Model for history
class ServiceRequestHistory extends Model {
    protected $fillable = ['request_id', 'action', 'old_value', 'new_value', 'changed_by'];
}
```

---

## 8. IMPLEMENTATION PRIORITY

### Phase 1 (Critical):

1. Fix file handling in ServiceRequestService
2. Add comprehensive field validation
3. Improve request number generation
4. Add database indexes

### Phase 2 (Important):

5. Implement visibility rules engine
6. Add request status transitions
7. Create audit trail
8. Add error codes

### Phase 3 (Nice-to-have):

9. Make payload resolver flexible
10. Database-driven locale config
11. Test coverage

---

## 9. CODE QUALITY CHECKLIST

- [ ] All field types supported with validation
- [ ] Comprehensive error messages
- [ ] File upload secure & validated
- [ ] Request numbers unique
- [ ] Visibility rules working
- [ ] Status transitions enforced
- [ ] Audit trail complete
- [ ] API documentation updated
- [ ] Unit tests > 80% coverage
- [ ] Database performance indices in place

---

## BEFORE/AFTER COMPARISON

| Aspect           | Before                           | After                                                 |
| ---------------- | -------------------------------- | ----------------------------------------------------- |
| Field Validation | Basic (required + single_choice) | Comprehensive (regex, date, file, range, cross-field) |
| File Handling    | Placeholder metadata only        | Real upload with storage & validation                 |
| Request Numbers  | Random 8 chars (collision risk)  | Timestamp + service + unique (guaranteed)             |
| Error Messages   | Generic                          | Specific with error codes                             |
| Status Workflow  | Read-only                        | Full lifecycle management                             |
| Performance      | No indexes                       | Indexed for all common queries                        |
| Audit Trail      | None                             | Complete history                                      |

---
