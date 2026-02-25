<?php

/**
 * Enhanced Logging Configuration
 * 
 * Cấu hình hệ thống ghi log toàn hệ thống với hỗ trợ:
 * - Async logging qua queue
 * - Smart path filtering
 * - Performance monitoring
 * - Security event tracking
 * - Database operation logging
 */

return [

    /**
     * Request Logger Configuration
     */
    'request_logger' => [
        // Sử dụng queue để log không đồng bộ (khuyến nghị cho production)
        'use_queue' => env('LOG_REQUEST_ASYNC', true),

        // Bật/tắt filtering paths
        'skip_paths_enabled' => env('LOG_SKIP_PATHS_ENABLED', true),

        // Các đường dẫn bỏ qua ghi log
        'skip_paths' => [
            '/up',
            '/health',
            '/health/check',
            '/api/health',
            '/telescope',
            '*.js',
            '*.css',
            '*.png',
            '*.jpg',
            '*.svg',
            '/api/v1/docs',
        ],
    ],

    /**
     * Action Logger Configuration
     */
    'action_logger' => [
        // Sử dụng queue để log hoạt động không đồng bộ
        'use_queue' => env('LOG_ACTION_ASYNC', true),

        // Lưu vào database
        'use_database' => env('LOG_ACTION_TO_DB', true),
    ],

    /**
     * Operation Logger Configuration
     */
    'operation_logger' => [
        // Sử dụng queue
        'use_queue' => env('LOG_OPERATION_ASYNC', true),

        // Lưu vào database
        'use_database' => env('LOG_OPERATION_TO_DB', true),

        // Ngưỡng để coi một query là "slow" (milliseconds)
        'slow_query_threshold' => env('LOG_SLOW_QUERY_THRESHOLD', 1000),

        // Ngưỡng để coi cache operation là "slow"
        'slow_cache_threshold' => env('LOG_SLOW_CACHE_THRESHOLD', 100),
    ],

    /**
     * Log Retention Policy
     */
    'retention' => [
        // Giữ log request bao nhiêu ngày
        'request_logs_days' => env('LOG_REQUEST_RETENTION_DAYS', 90),

        // Giữ log hoạt động bao nhiêu ngày
        'action_logs_days' => env('LOG_ACTION_RETENTION_DAYS', 180),

        // Giữ log truy vấn chậm bao nhiêu ngày
        'operation_logs_days' => env('LOG_OPERATION_RETENTION_DAYS', 60),

        // Giữ log bảo mật bao nhiêu ngày
        'security_logs_days' => env('LOG_SECURITY_RETENTION_DAYS', 365),
    ],

    /**
     * Queue Configuration
     */
    'queue' => [
        // Queue name để ghi log
        'name' => env('LOG_QUEUE_NAME', 'logs'),

        // Batch size khi ghi log (số lượng logs ghi cùng lúc)
        'batch_size' => env('LOG_BATCH_SIZE', 100),

        // Khoảng thời gian flush batch (seconds)
        'batch_timeout' => env('LOG_BATCH_TIMEOUT', 5),
    ],

    /**
     * Performance Monitoring
     */
    'performance' => [
        // Bật tự động ghi log các query chậm
        'log_slow_queries' => env('LOG_SLOW_QUERIES', true),

        // Bật tự động ghi log cache operations
        'log_cache_operations' => env('LOG_CACHE_OPERATIONS', true),

        // Bật tự động ghi log external API calls
        'log_api_calls' => env('LOG_API_CALLS', true),
    ],

    /**
     * Security Logging
     */
    'security' => [
        // Bật ghi log sự kiện bảo mật
        'enabled' => env('LOG_SECURITY_EVENTS', true),

        // Ghi log tất cả thay đổi quyền hạn
        'log_permission_changes' => env('LOG_PERMISSION_CHANGES', true),

        // Ghi log tất cả truy cập bị từ chối
        'log_access_denied' => env('LOG_ACCESS_DENIED', true),

        // Ghi log failed login attempts
        'log_failed_logins' => env('LOG_FAILED_LOGINS', true),
    ],

];
