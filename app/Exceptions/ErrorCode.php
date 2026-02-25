<?php

namespace App\Exceptions;

enum ErrorCode: string
{
    // === Validation Errors ===
    case VALIDATION_ERROR = 'VALIDATION_001';
    case INVALID_JSON = 'VALIDATION_002';
    case MISSING_REQUIRED_FIELD = 'VALIDATION_003';

    // === Field Validation ===
    case FIELD_REQUIRED = 'FIELD_001';
    case FIELD_INVALID_TYPE = 'FIELD_002';
    case FIELD_INVALID_FORMAT = 'FIELD_003';
    case FIELD_OUT_OF_RANGE = 'FIELD_004';
    case FIELD_INVALID_PATTERN = 'FIELD_005';
    case FIELD_INVALID_OPTION = 'FIELD_006';
    case FIELD_MIN_LENGTH = 'FIELD_007';
    case FIELD_MAX_LENGTH = 'FIELD_008';
    case FIELD_INVALID_CHOICE = 'FIELD_009';

    // === Date/Time ===
    case DATE_INVALID_FORMAT = 'DATE_001';
    case DATE_INVALID_RANGE = 'DATE_002';
    case DATETIME_INVALID_FORMAT = 'DATETIME_001';

    // === Numeric ===
    case NUMERIC_INVALID = 'NUMERIC_001';
    case NUMERIC_OUT_OF_RANGE = 'NUMERIC_002';
    case NUMERIC_DECIMAL_NOT_ALLOWED = 'NUMERIC_003';

    // === Contact ===
    case EMAIL_INVALID = 'EMAIL_001';
    case PHONE_INVALID = 'PHONE_001';
    case URL_INVALID = 'URL_001';

    // === File ===
    case FILE_REQUIRED = 'FILE_001';
    case FILE_TOO_LARGE = 'FILE_002';
    case FILE_INVALID_TYPE = 'FILE_003';
    case FILE_INVALID_EXTENSION = 'FILE_004';
    case FILE_UPLOAD_FAILED = 'FILE_005';

    // === Not Found ===
    case SERVICE_NOT_FOUND = 'NOT_FOUND_001';
    case REQUEST_NOT_FOUND = 'NOT_FOUND_002';
    case CATEGORY_NOT_FOUND = 'NOT_FOUND_003';
    case FORM_TEMPLATE_NOT_FOUND = 'NOT_FOUND_004';
    case FORM_VERSION_NOT_FOUND = 'NOT_FOUND_005';
    case FIELD_NOT_FOUND = 'NOT_FOUND_006';

    // === Business ===
    case NO_ACTIVE_FORM_VERSION = 'BUSINESS_001';
    case INVALID_STATUS_TRANSITION = 'BUSINESS_002';
    case DUPLICATE_REQUEST = 'BUSINESS_003';
    case SERVICE_NOT_ACTIVE = 'BUSINESS_004';
    case FORM_ALREADY_PUBLISHED = 'BUSINESS_005';

    // === Auth ===
    case UNAUTHORIZED = 'AUTH_001';
    case FORBIDDEN = 'AUTH_002';
    case TOKEN_EXPIRED = 'AUTH_003';
    case TOKEN_INVALID = 'AUTH_004';

    // === Server ===
    case INTERNAL_SERVER_ERROR = 'SERVER_001';
    case DATABASE_ERROR = 'SERVER_002';
    case STORAGE_ERROR = 'SERVER_003';
    case TRANSACTION_FAILED = 'SERVER_004';

    public function message(): string
    {
        return match ($this) {
            self::VALIDATION_ERROR => 'Validation failed',
            self::INVALID_JSON => 'Invalid JSON format',
            self::MISSING_REQUIRED_FIELD => 'Missing required field',

            self::FIELD_REQUIRED => 'This field is required',
            self::FIELD_INVALID_TYPE => 'Invalid field type',
            self::FIELD_INVALID_FORMAT => 'Invalid format',
            self::FIELD_OUT_OF_RANGE => 'Value out of range',
            self::FIELD_INVALID_PATTERN => 'Invalid pattern',
            self::FIELD_INVALID_OPTION => 'Invalid option',
            self::FIELD_MIN_LENGTH => 'Value too short',
            self::FIELD_MAX_LENGTH => 'Value too long',
            self::FIELD_INVALID_CHOICE => 'Invalid choice',

            self::DATE_INVALID_FORMAT => 'Invalid date format',
            self::DATE_INVALID_RANGE => 'Date out of range',
            self::DATETIME_INVALID_FORMAT => 'Invalid datetime format',

            self::NUMERIC_INVALID => 'Must be numeric',
            self::NUMERIC_OUT_OF_RANGE => 'Numeric value out of range',
            self::NUMERIC_DECIMAL_NOT_ALLOWED => 'Decimal not allowed',

            self::EMAIL_INVALID => 'Invalid email address',
            self::PHONE_INVALID => 'Invalid phone number',
            self::URL_INVALID => 'Invalid URL',

            self::FILE_REQUIRED => 'File is required',
            self::FILE_TOO_LARGE => 'File too large',
            self::FILE_INVALID_TYPE => 'Invalid file type',
            self::FILE_INVALID_EXTENSION => 'Invalid file extension',
            self::FILE_UPLOAD_FAILED => 'File upload failed',

            self::SERVICE_NOT_FOUND => 'Service not found',
            self::REQUEST_NOT_FOUND => 'Request not found',
            self::CATEGORY_NOT_FOUND => 'Category not found',
            self::FORM_TEMPLATE_NOT_FOUND => 'Form template not found',
            self::FORM_VERSION_NOT_FOUND => 'Form version not found',
            self::FIELD_NOT_FOUND => 'Field not found',

            self::NO_ACTIVE_FORM_VERSION => 'No active form version',
            self::INVALID_STATUS_TRANSITION => 'Invalid status transition',
            self::DUPLICATE_REQUEST => 'Duplicate request',
            self::SERVICE_NOT_ACTIVE => 'Service not active',
            self::FORM_ALREADY_PUBLISHED => 'Form already published',

            self::UNAUTHORIZED => 'Authentication required',
            self::FORBIDDEN => 'Permission denied',
            self::TOKEN_EXPIRED => 'Token expired',
            self::TOKEN_INVALID => 'Invalid token',

            self::INTERNAL_SERVER_ERROR => 'Internal server error',
            self::DATABASE_ERROR => 'Database error',
            self::STORAGE_ERROR => 'Storage error',
            self::TRANSACTION_FAILED => 'Transaction failed',
        };
    }

    public function httpStatus(): int
    {
        return match ($this) {
            self::UNAUTHORIZED,
            self::TOKEN_EXPIRED,
            self::TOKEN_INVALID => 401,

            self::FORBIDDEN => 403,

            self::SERVICE_NOT_FOUND,
            self::REQUEST_NOT_FOUND,
            self::CATEGORY_NOT_FOUND,
            self::FORM_TEMPLATE_NOT_FOUND,
            self::FORM_VERSION_NOT_FOUND,
            self::FIELD_NOT_FOUND => 404,

            self::NO_ACTIVE_FORM_VERSION,
            self::INVALID_STATUS_TRANSITION,
            self::DUPLICATE_REQUEST,
            self::SERVICE_NOT_ACTIVE,
            self::FORM_ALREADY_PUBLISHED => 409,

            self::INTERNAL_SERVER_ERROR,
            self::DATABASE_ERROR,
            self::STORAGE_ERROR,
            self::TRANSACTION_FAILED => 500,

            default => 400,
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::VALIDATION_ERROR,
            self::INVALID_JSON,
            self::MISSING_REQUIRED_FIELD,
            self::FIELD_REQUIRED,
            self::FIELD_INVALID_TYPE,
            self::FIELD_INVALID_FORMAT,
            self::FIELD_OUT_OF_RANGE,
            self::FIELD_INVALID_PATTERN,
            self::FIELD_INVALID_OPTION,
            self::FIELD_MIN_LENGTH,
            self::FIELD_MAX_LENGTH,
            self::FIELD_INVALID_CHOICE,
            self::DATE_INVALID_FORMAT,
            self::DATE_INVALID_RANGE,
            self::DATETIME_INVALID_FORMAT,
            self::NUMERIC_INVALID,
            self::NUMERIC_OUT_OF_RANGE,
            self::NUMERIC_DECIMAL_NOT_ALLOWED,
            self::EMAIL_INVALID,
            self::PHONE_INVALID,
            self::URL_INVALID,
            self::FILE_REQUIRED,
            self::FILE_TOO_LARGE,
            self::FILE_INVALID_TYPE,
            self::FILE_INVALID_EXTENSION,
            self::FILE_UPLOAD_FAILED => 'validation',

            self::SERVICE_NOT_FOUND,
            self::REQUEST_NOT_FOUND,
            self::CATEGORY_NOT_FOUND,
            self::FORM_TEMPLATE_NOT_FOUND,
            self::FORM_VERSION_NOT_FOUND,
            self::FIELD_NOT_FOUND => 'not_found',

            self::NO_ACTIVE_FORM_VERSION,
            self::INVALID_STATUS_TRANSITION,
            self::DUPLICATE_REQUEST,
            self::SERVICE_NOT_ACTIVE,
            self::FORM_ALREADY_PUBLISHED => 'business',

            self::UNAUTHORIZED,
            self::FORBIDDEN,
            self::TOKEN_EXPIRED,
            self::TOKEN_INVALID => 'auth',

            self::INTERNAL_SERVER_ERROR,
            self::DATABASE_ERROR,
            self::STORAGE_ERROR,
            self::TRANSACTION_FAILED => 'server',
        };
    }

    public function isRetryable(): bool
    {
        return match ($this) {
            self::DATABASE_ERROR,
            self::STORAGE_ERROR,
            self::TRANSACTION_FAILED,
            self::INTERNAL_SERVER_ERROR => true,
            default => false,
        };
    }
}