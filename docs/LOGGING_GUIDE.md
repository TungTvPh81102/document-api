# 📊 HỆ THỐNG GHI LOG TOÀN DIỆN - HƯỚNG DẪN

## 📋 Mục đích

Hệ thống ghi log nâng cao này ghi lại mọi hoạt động trong ứng dụng:

- ✅ Mọi HTTP request/response
- ✅ Mọi thao tác kinh doanh (CRUD, trạng thái thay đổi)
- ✅ Mọi truy vấn database chậm
- ✅ Mọi sự kiện xác thực (login, logout)
- ✅ Mọi sự kiện bảo mật (quyền, truy cập bị từ chối)
- ✅ Mọi cache operations
- ✅ Mọi external API calls

## 🚀 Tính năng chính

### 1. **Async Logging (Ghi log không đồng bộ)**

- Không chặn request chính
- Sử dụng Queue để xử lý log
- Tăng hiệu năng 2-3x

### 2. **Smart Path Filtering**

- Tự động bỏ qua health checks
- Tự động bỏ qua static files
- Tự động bỏ qua telescope

### 3. **Performance Monitoring**

- Tự động phát hiện slow queries (> 1000ms)
- Tự động phát hiện slow cache operations (> 100ms)
- Tự động phát hiện slow API calls (> 5000ms)

### 4. **Security Event Tracking**

- Ghi log tất cả sự kiện xác thực
- Ghi log failed logins
- Ghi log sự kiện bảo mật
- Ghi log thay đổi quyền hạn

### 5. **Comprehensive Analytics**

- Lệnh `logs:analytics` để xem thống kê
- Reports theo ngày, tuần, tháng
- Top slow queries
- Top error routes

### 6. **Log Archiving & Cleanup**

- Tự động lưu trữ log cũ
- Tự động xóa log theo retention policy
- Lệnh `logs:archive` để lưu trữ
- Lệnh `logs:cleanup` để dọn dẹp

## 🔧 Cấu hình

### Environment Variables

```env
# Async logging configuration
LOG_REQUEST_ASYNC=true              # Ghi log request không đồng bộ
LOG_ACTION_ASYNC=true               # Ghi log hoạt động không đồng bộ
LOG_OPERATION_ASYNC=true            # Ghi log truy vấn không đồng bộ

# Path filtering
LOG_SKIP_PATHS_ENABLED=true         # Bật smart path filtering

# Performance thresholds
LOG_SLOW_QUERY_THRESHOLD=1000       # Query chậm hơn 1000ms
LOG_SLOW_CACHE_THRESHOLD=100        # Cache chậm hơn 100ms

# Retention policy (days)
LOG_REQUEST_RETENTION_DAYS=90       # Giữ request logs 90 ngày
LOG_ACTION_RETENTION_DAYS=180       # Giữ action logs 180 ngày
LOG_OPERATION_RETENTION_DAYS=60     # Giữ operation logs 60 ngày
LOG_SECURITY_RETENTION_DAYS=365     # Giữ security logs 365 ngày

# Queue
LOG_QUEUE_NAME=logs                 # Queue name để ghi log
LOG_BATCH_SIZE=100                  # Batch size
LOG_BATCH_TIMEOUT=5                 # Batch timeout (seconds)

# Performance monitoring
LOG_SLOW_QUERIES=true               # Ghi log slow queries
LOG_CACHE_OPERATIONS=true           # Ghi log cache operations
LOG_API_CALLS=true                  # Ghi log API calls

# Security
LOG_SECURITY_EVENTS=true            # Ghi log bảo mật
LOG_PERMISSION_CHANGES=true         # Ghi log thay đổi quyền
LOG_ACCESS_DENIED=true              # Ghi log truy cập bị từ chối
LOG_FAILED_LOGINS=true              # Ghi log failed logins
```

## 📚 Cách sử dụng

### 1. Log HTTP Requests (Tự động)

Mọi request được ghi log tự động thông qua middleware:

```php
// LogHttpRequestsMiddleware - tự động log tất cả requests
// Không cần làm gì, middleware đã xử lý
```

### 2. Log Business Actions

```php
use App\Services\ActionLoggerService;

class UserController extends Controller
{
    public function __construct(private ActionLoggerService $actionLogger) {}

    public function store(Request $request)
    {
        $user = User::create($request->validated());

        // Log hành động tạo user
        $this->actionLogger->logModelAction(
            model: $user,
            action: 'create',
            changes: ['name' => $user->name, 'email' => $user->email],
            description: 'User created via API'
        );

        return UserResource::make($user);
    }

    public function update(Request $request, User $user)
    {
        $originalData = $user->only(['name', 'email', 'phone']);

        $user->update($request->validated());

        // Log hành động cập nhật
        $this->actionLogger->logModelAction(
            model: $user,
            action: 'update',
            changes: [
                'from' => $originalData,
                'to' => $user->only(['name', 'email', 'phone']),
            ],
            description: 'User updated via API'
        );

        return UserResource::make($user);
    }

    public function destroy(User $user)
    {
        $userName = $user->name;
        $user->delete();

        // Log hành động xóa
        $this->actionLogger->logModelAction(
            model: $user,
            action: 'delete',
            description: "User '{$userName}' deleted"
        );

        return response()->json(['message' => 'Deleted']);
    }
}
```

### 3. Log Database Operations

```php
use App\Services\OperationLoggerService;

class UserService
{
    public function __construct(private OperationLoggerService $opLogger) {}

    public function createBatch(array $users)
    {
        $start = microtime(true);

        $results = collect($users)->map(fn($data) => User::create($data));

        $duration = (microtime(true) - $start) * 1000;

        // Log database operation
        $this->opLogger->logDatabaseOperation(
            operation: 'insert',
            table: 'users',
            rowsAffected: count($users),
            durationMs: $duration
        );

        return $results;
    }
}
```

### 4. Log Cache Operations

```php
use App\Services\OperationLoggerService;

class CacheService
{
    public function __construct(private OperationLoggerService $opLogger) {}

    public function getOrCache(string $key, callable $callback, int $ttl = 3600)
    {
        $start = microtime(true);

        // Try to get from cache
        $value = cache($key);
        $duration = (microtime(true) - $start) * 1000;
        $hit = $value !== null;

        if ($hit) {
            // Log cache hit
            $this->opLogger->logCacheOperation(
                operation: 'get',
                key: $key,
                durationMs: $duration,
                hit: true,
                value: $value
            );
            return $value;
        }

        // Compute value
        $start = microtime(true);
        $value = $callback();
        $duration = (microtime(true) - $start) * 1000;

        // Cache it
        cache([$key => $value], $ttl);

        // Log cache miss & set
        $this->opLogger->logCacheOperation(
            operation: 'put',
            key: $key,
            durationMs: $duration,
            hit: false,
            value: $value
        );

        return $value;
    }
}
```

### 5. Log Authentication Events

```php
use App\Services\ActionLoggerService;

class LoginController extends Controller
{
    public function __construct(private ActionLoggerService $actionLogger) {}

    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            // Log failed login
            $this->actionLogger->logAuthAction(
                action: 'failed_login',
                user: null,
                reason: 'Invalid credentials'
            );

            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        auth()->login($user);

        // Log successful login
        $this->actionLogger->logAuthAction(
            action: 'login',
            user: $user,
            reason: 'Manual login'
        );

        return response()->json(['token' => $user->createToken('api')->plainTextToken]);
    }

    public function logout()
    {
        $user = auth()->user();

        $this->actionLogger->logAuthAction(
            action: 'logout',
            user: $user
        );

        auth()->logout();
        return response()->json(['message' => 'Logged out']);
    }
}
```

### 6. Log Security Events

```php
use App\Services\ActionLoggerService;

class PermissionMiddleware
{
    public function __construct(private ActionLoggerService $actionLogger) {}

    public function handle($request, Closure $next, $permission)
    {
        if (!auth()->user()?->hasPermission($permission)) {
            // Log security event
            $this->actionLogger->logSecurityEvent(
                eventType: 'permission_denied',
                description: "User denied access to {$permission}",
                context: [
                    'requested_permission' => $permission,
                    'user_id' => auth()->id(),
                    'route' => $request->getPathInfo(),
                ]
            );

            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
```

### 7. Log API Calls

```php
use App\Services\OperationLoggerService;

class ExternalApiService
{
    public function __construct(private OperationLoggerService $opLogger) {}

    public function callApi(string $method, string $url, array $data = [])
    {
        $start = microtime(true);

        try {
            $response = Http::withTimeout(10)->retry(2, 100)
                ->{strtolower($method)}($url, $data);

            $duration = (microtime(true) - $start) * 1000;

            // Log API call
            $this->opLogger->logApiCall(
                method: $method,
                url: $url,
                durationMs: $duration,
                statusCode: $response->status()
            );

            return $response;
        } catch (\Exception $e) {
            $duration = (microtime(true) - $start) * 1000;

            // Log API call error
            $this->opLogger->logApiCall(
                method: $method,
                url: $url,
                durationMs: $duration,
                statusCode: 0,
                errorMessage: $e->getMessage()
            );

            throw $e;
        }
    }
}
```

## 📊 Xem Analytics & Reports

### Command: logs:analytics

```bash
# Xem analytics cho 7 ngày gần nhất (default)
php artisan logs:analytics

# Xem analytics cho 30 ngày
php artisan logs:analytics --days=30

# Output mẫu:
# === LOG ANALYTICS (Last 7 days) ===
#
# REQUEST STATISTICS:
#   Total requests: 15420
#   By method:
#     GET: 8920
#     POST: 4500
#     PUT: 1200
#     DELETE: 800
#   By status code:
#     200: 14800
#     400: 400
#     404: 200
#     500: 20
```

### Xem Slow Queries

```php
use App\Services\OperationLoggerService;

$opLogger = app(OperationLoggerService::class);

// Get query statistics
$stats = $opLogger->getQueryStats();
// ['total_queries' => 5000, 'avg_duration_ms' => 45.2, 'slow_queries_pct' => 2.5]

// Get specific table stats
$stats = $opLogger->getQueryStats('users');

// Get slow operations
$slowOps = $opLogger->getSlowOperations('query', 50);
// Returns 50 slowest queries
```

### Xem trong Database

```bash
# Top slow queries
SELECT query, COUNT(*) as count, MAX(duration_ms) as max_duration
FROM operation_logs
WHERE is_slow = true AND type = 'query'
GROUP BY query
ORDER BY max_duration DESC
LIMIT 10;

# Top error routes
SELECT route, COUNT(*) as count
FROM cim_sql_log
WHERE is_error = true
GROUP BY route
ORDER BY count DESC
LIMIT 10;

# Most active users
SELECT user_id, COUNT(*) as request_count
FROM cim_sql_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY user_id
ORDER BY request_count DESC
LIMIT 10;

# Security events
SELECT event_type, COUNT(*) as count
FROM security_events
WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY event_type
ORDER BY count DESC;
```

## 🧹 Dọn dẹp & Lưu trữ Logs

### Archive old logs

```bash
# Archive logs older than 90 days (default)
php artisan logs:archive

# Archive logs older than 30 days
php artisan logs:archive --days=30
```

### Cleanup old logs

```bash
# Delete logs theo retention policy
php artisan logs:cleanup

# Confirm before deleting
php artisan logs:cleanup --force
```

### Schedule automatic cleanup

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Archive logs weekly
    $schedule->command('logs:archive')->weekly();

    // Cleanup logs daily
    $schedule->command('logs:cleanup')->daily()->at('03:00');

    // Analytics report daily
    $schedule->command('logs:analytics --days=1')->daily()->at('04:00');
}
```

## 📈 Tối ưu hiệu năng

### 1. Sử dụng Async Logging

```env
LOG_REQUEST_ASYNC=true
LOG_ACTION_ASYNC=true
LOG_OPERATION_ASYNC=true
```

**Lợi ích:**

- Request không bị chặn
- Tốc độ tăng 2-3x
- Không ảnh hưởng đến user experience

### 2. Điều chỉnh Thresholds

```env
# Chỉ log query chậm hơn 500ms thay vì 1000ms
LOG_SLOW_QUERY_THRESHOLD=500

# Chỉ log cache chậm hơn 50ms
LOG_SLOW_CACHE_THRESHOLD=50
```

### 3. Batch Processing

```env
LOG_BATCH_SIZE=100          # Ghi 100 logs cùng lúc
LOG_BATCH_TIMEOUT=5         # Timeout 5 giây
```

### 4. Cleanup Strategy

```env
# Giảm retention period để tiết kiệm space
LOG_REQUEST_RETENTION_DAYS=30    # Thay vì 90
LOG_ACTION_RETENTION_DAYS=90     # Thay vì 180
```

## 🔒 Bảo mật

### Sensitive Data Redaction

Tất cả dữ liệu nhạy cảm tự động được che giấu:

- `password` → `***REDACTED***`
- `token` → `***REDACTED***`
- `api_key` → `***REDACTED***`
- `credit_card` → `***REDACTED***`

### Security Event Logging

```env
LOG_SECURITY_EVENTS=true
LOG_PERMISSION_CHANGES=true
LOG_ACCESS_DENIED=true
LOG_FAILED_LOGINS=true
```

## 📋 Database Schema

### cim_sql_log (HTTP Requests)

- id, request_id, level, service
- method, url, route, status_code
- function_name, logic_name
- parameters, response_data, fail_result
- duration_ms, user_id, ip_address, user_agent
- is_error, created_at, updated_at

### action_logs (Business Operations)

- id, user_id, model_class, model_id, model_name
- action (create/update/delete/restore)
- changes (JSON), description
- ip_address, user_agent, timestamp

### auth_logs (Authentication)

- id, user_id, action
- description, ip_address, user_agent, timestamp

### operation_logs (Database/Cache/API)

- id, operation, type, query, bindings
- duration_ms, is_slow, rows_affected
- user_id, error, timestamp

### security_events (Security)

- id, user_id, event_type
- description, context (JSON)
- ip_address, user_agent, timestamp

## 🎯 Best Practices

✅ **DO:**

- Luôn log các hành động quan trọng
- Luôn log các lỗi
- Luôn log các sự kiện bảo mật
- Sử dụng async logging trong production
- Thiết lập retention policy phù hợp
- Chạy cleanup regularly
- Monitor slow queries

❌ **DON'T:**

- Không log mật khẩu hay token
- Không log mọi query (quá nhiều dữ liệu)
- Không sử dụng synchronous logging trong production
- Không bỏ qua log errors
- Không giữ logs vô hạn

## 🚨 Troubleshooting

### Logs không được ghi

1. Kiểm tra queue đang chạy:

    ```bash
    php artisan queue:work
    ```

2. Kiểm tra config:

    ```bash
    php artisan config:show logging-enhanced
    ```

3. Kiểm tra database tables:
    ```bash
    php artisan migrate
    ```

### Server chậm

1. Kiểm tra enable async logging:

    ```env
    LOG_REQUEST_ASYNC=true
    ```

2. Cleanup old logs:

    ```bash
    php artisan logs:cleanup
    ```

3. Tăng batch size:
    ```env
    LOG_BATCH_SIZE=500
    ```

### Database quá lớn

1. Archive old logs:

    ```bash
    php artisan logs:archive
    ```

2. Giảm retention days:
    ```env
    LOG_REQUEST_RETENTION_DAYS=30
    ```

## 📞 Support

Nếu có vấn đề, kiểm tra:

- `storage/logs/` - application logs
- `storage/logs/api.log` - API logs
- `storage/logs/user_activity.log` - user logs
- `storage/logs/database.log` - database logs
- `storage/logs/security.log` - security logs
