<?php

namespace App\Services;

use App\Models\DynamicService\Service;
use App\Models\DynamicService\ServiceRequest;
use App\Models\DynamicService\ServiceRequestAnswer;
use App\Models\DynamicService\ServiceRequestAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServiceRequestService
{
    public function __construct(
        private FormEngineService $formEngine
    ) {}

    /**
     * Submit a new service request with comprehensive validation.
     */
    public function submitRequest(Service $service, array $data, string $requesterId): ServiceRequest
    {
        $version = $service->currentVersion;
        if (!$version) {
            throw new \Exception("Service has no published form version.");
        }

        // 1. Validate submission
        $answers = $data['answers'] ?? [];
        $errors = $this->formEngine->validateSubmission($version, $answers);
        if (!empty($errors)) {
            throw new \Illuminate\Validation\ValidationException(
                null,
                new \Illuminate\Support\MessageBag($errors)
            );
        }

        return DB::transaction(function () use ($service, $version, $data, $requesterId) {
            // 2. Create Request with improved request number
            $request = ServiceRequest::query()->create([
                'request_no'      => $this->generateRequestNumber($service),
                'service_id'      => $service->id,
                'form_version_id' => $version->id,
                'requester_id'    => $requesterId,
                'status'          => 'submitted',
                'submitted_at'    => now(),
            ]);

            // 3. Save Answers
            foreach ($data['answers'] ?? [] as $fieldKey => $value) {
                $field = $version->fields()->where('field_key', $fieldKey)->first();
                if (!$field) continue;

                $this->saveAnswer($request, $field, $value);
            }

            // 4. Handle Attachments with REAL file processing
            foreach ($data['attachments'] ?? [] as $fieldKey => $files) {
                $field = $version->fields()->where('field_key', $fieldKey)->first();
                if (!$field || $field->field_type !== 'file') continue;

                // Support both single file and array of files
                $fileList = $this->normalizeFileInput($files);

                foreach ($fileList as $file) {
                    if ($file instanceof UploadedFile) {
                        $this->processAttachment($request, $field, $file, $requesterId);
                    }
                }
            }

            return $request->load(['answers.field', 'attachments', 'service']);
        });
    }

    /**
     * Generate unique request number with timestamp and collision prevention.
     * Format: REQ-YYYYMMDDHHMISS-SSS-XXXX
     * - YYYYMMDDHHMISS: timestamp for ordering
     * - SSS: last 3 chars of service code
     * - XXXX: random 4 chars for uniqueness
     */
    private function generateRequestNumber(Service $service): string
    {
        $timestamp = now()->format('YmdHis');
        $serviceSuffix = substr($service->svc_code, -3) ?? 'SVC';
        $random = strtoupper(Str::random(4));
        $baseNumber = "REQ-{$timestamp}-{$serviceSuffix}-{$random}";

        // Ensure absolute uniqueness (with retry)
        $requestNo = $baseNumber;
        $retries = 0;
        while (ServiceRequest::where('request_no', $requestNo)->exists() && $retries < 10) {
            $random = strtoupper(Str::random(4));
            $requestNo = "REQ-{$timestamp}-{$serviceSuffix}-{$random}";
            $retries++;
        }

        if ($retries >= 10) {
            throw new \Exception("Failed to generate unique request number after {$retries} retries");
        }

        return $requestNo;
    }

    /**
     * Normalize file input - support both single file and array of files.
     */
    private function normalizeFileInput(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (is_array($files)) {
            // If first element is UploadedFile, treat whole array as file list
            if (isset($files[0]) && $files[0] instanceof UploadedFile) {
                return $files;
            }
            // If keys are file properties, wrap in array
            if (isset($files['originalName']) || isset($files['path'])) {
                return [$files];
            }
        }

        return [];
    }

    /**
     * Process file attachment with validation and storage.
     */
    private function processAttachment(
        ServiceRequest $request,
        $field,
        UploadedFile $file,
        string $uploadedBy
    ): void {
        // 1. Validate file
        $validation = $field->validation_json ?? [];
        $this->validateFile($file, $validation);

        // 2. Store file in storage disk
        $storagePath = "service-requests/{$request->id}/";
        $filename = $file->hashName();
        $path = $file->storeAs($storagePath, $filename, 'uploads');

        if (!$path) {
            throw new \Exception("Failed to store file for field {$field->field_key}");
        }

        // 3. Create attachment record
        $request->attachments()->create([
            'field_id'    => $field->id,
            'file_name'   => $file->getClientOriginalName(),
            'file_path'   => $path,
            'mime_type'   => $file->getMimeType(),
            'file_size'   => $file->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /**
     * Validate uploaded file against field validation rules.
     */
    private function validateFile(UploadedFile $file, array $validation): void
    {
        // Validate file size
        $maxSize = $validation['maxSize'] ?? 5242880; // 5MB default
        if ($file->getSize() > $maxSize) {
            $maxSizeMB = round($maxSize / 1024 / 1024, 2);
            throw new \Exception("File exceeds maximum size of {$maxSizeMB}MB");
        }

        // Validate mime type
        $allowedMimes = $validation['allowedMimes'] ?? [];
        if (!empty($allowedMimes)) {
            $fileMime = $file->getMimeType();
            if (!in_array($fileMime, $allowedMimes)) {
                throw new \Exception("File type '{$fileMime}' not allowed. Allowed: " . implode(', ', $allowedMimes));
            }
        }

        // Validate file extension
        $allowedExtensions = $validation['allowedExtensions'] ?? [];
        if (!empty($allowedExtensions)) {
            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, $allowedExtensions)) {
                throw new \Exception("File extension '{$extension}' not allowed. Allowed: " . implode(', ', $allowedExtensions));
            }
        }
    }

    /**
     * Save an answer with proper type conversion based on field type.
     */
    private function saveAnswer(ServiceRequest $request, $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            // Skip empty values for non-required fields
            if (!$field->is_required) {
                return;
            }
        }

        $answerData = [
            'request_id' => $request->id,
            'field_id'   => $field->id,
        ];

        try {
            switch ($field->field_type) {
                case 'short_text':
                case 'employee_code':
                case 'plant_code':
                case 'email':
                case 'phone':
                case 'url':
                case 'single_choice':
                    $answerData['value_text'] = (string) $value;
                    break;

                case 'long_text':
                case 'textarea':
                    $answerData['value_text'] = (string) $value;
                    break;

                case 'multi_choice':
                    $answerData['value_json'] = is_array($value) ? $value : [$value];
                    break;

                case 'date':
                    $validation = $field->validation_json ?? [];
                    $format = $validation['format'] ?? 'Y-m-d';
                    $date = \DateTime::createFromFormat($format, (string)$value);

                    if ($date === false) {
                        throw new \Exception("Invalid date format. Expected: {$format}");
                    }

                    $answerData['value_date'] = $date->format('Y-m-d');
                    break;

                case 'datetime':
                    $dateTime = new \DateTime((string)$value);
                    $answerData['value_text'] = $dateTime->format('Y-m-d H:i:s');
                    break;

                case 'numeric':
                    if (!is_numeric($value)) {
                        throw new \Exception("Value must be numeric");
                    }
                    // Store as decimal in value_number, also keep text for audit
                    $answerData['value_number'] = (float) $value;
                    $answerData['value_text'] = (string) $value;
                    break;

                case 'file':
                    // File handling is done separately in processAttachment
                    return;

                default:
                    // Generic handling for unknown types
                    if (is_array($value) || is_object($value)) {
                        $answerData['value_json'] = $value;
                    } else {
                        $answerData['value_text'] = (string) $value;
                    }
            }

            ServiceRequestAnswer::query()->create($answerData);
        } catch (\Exception $e) {
            throw new \Exception("Error saving answer for field '{$field->field_key}': " . $e->getMessage());
        }
    }
}
