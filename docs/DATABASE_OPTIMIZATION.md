# 🚀 HƯỚNG DẪN TỐI ƯU HÓA DATABASE & CACHE

## 📊 DATABASE OPTIMIZATION

### 1. Query Optimization

#### Problem: N+1 Query

```php
// ❌ BAD - N+1 query problem
$users = User::all();
foreach ($users as $user) {
    echo $user->roles()->count(); // 1 + N queries
}

// ✅ GOOD - Use eager loading
$users = User::with('roles')->get(); // 2 queries total
foreach ($users as $user) {
    echo $user->roles()->count(); // Already loaded
}

// ✅ BETTER - Count aggregation
$users = User::withCount('roles')->get(); // 1 query
foreach ($users as $user) {
    echo $user->roles_count; // No additional queries
}
```

#### Implement OperationLogger to detect N+1

```php
// app/Listeners/LogQueryListener.php
use Illuminate\Database\Events\QueryExecuted;
use App\Services\OperationLoggerService;

class LogQueryListener
{
    public function __construct(private OperationLoggerService $opLogger) {}

    public function handle(QueryExecuted $event)
    {
        // Log all queries for debugging
        $this->opLogger->logQuery(
            sql: $event->sql,
            bindings: $event->bindings,
            durationMs: $event->time,
            connection: $event->connectionName
        );
    }
}

// Register in EventServiceProvider
protected $listen = [
    \Illuminate\Database\Events\QueryExecuted::class => [
        \App\Listeners\LogQueryListener::class,
    ],
];
```

### 2. Index Strategy

```php
// Create proper indexes in migrations
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('email')->unique(); // Single column index
    $table->string('name');
    $table->boolean('is_active')->default(true);
    $table->timestamp('created_at')->nullable();

    // Composite index for common queries
    $table->index(['is_active', 'created_at']);

    // Index for search
    $table->fullText(['name', 'email']); // Full-text search
});

// Use composite index efficiently
$users = User::where('is_active', true)
    ->where('created_at', '>=', now()->subDays(30))
    ->get(); // Fast - uses composite index
```

### 3. Select Only Required Columns

```php
// ❌ BAD - Select all columns
$users = User::all();

// ✅ GOOD - Select only needed columns
$users = User::select('id', 'name', 'email')->get();

// ✅ BETTER - Use projections for reporting
$userReport = User::selectRaw('
    id,
    name,
    email,
    COUNT(DISTINCT posts.id) as post_count,
    SUM(CASE WHEN orders.status = \"completed\" THEN orders.total ELSE 0 END) as total_spent
')
->leftJoin('posts', 'users.id', '=', 'posts.user_id')
->leftJoin('orders', 'users.id', '=', 'orders.user_id')
->groupBy('users.id', 'users.name', 'users.email')
->get();
```

### 4. Pagination for Large Datasets

```php
// ❌ BAD - Load all records
$users = User::all(); // Memory intensive

// ✅ GOOD - Paginate
$users = User::paginate(50);

// ✅ BETTER - Cursor pagination for large tables
$users = User::orderBy('id')->cursorPaginate(50);

// ✅ BEST - Lazy for processing
$users = User::lazy()->each(function ($user) {
    // Process in memory-efficient chunks
    $user->process();
});
```

### 5. Batch Operations

```php
// ❌ BAD - Update one by one
foreach ($users as $user) {
    $user->update(['last_login' => now()]);
}

// ✅ GOOD - Batch update
User::whereIn('id', $users->pluck('id'))
    ->update(['last_login' => now()]);

// ✅ BETTER - Chunk and process
User::query()
    ->whereNull('last_login')
    ->chunk(1000, function ($users) {
        User::whereIn('id', $users->pluck('id'))
            ->update(['last_login' => now()]);
    });

// Log batch operation
$this->opLogger->logDatabaseOperation(
    operation: 'batch_update',
    table: 'users',
    rowsAffected: $affectedCount,
    durationMs: $duration
);
```

### 6. Connection Pooling

```env
# .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=document
DB_USERNAME=root
DB_PASSWORD=secret

# Connection pool settings
DB_POOL_MIN=5
DB_POOL_MAX=20
DB_POOL_TIMEOUT=30
```

### 7. Read/Write Separation

```php
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    'url' => env('DB_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'read' => [
        'host' => env('DB_READ_HOST', '127.0.0.1'),
    ],
    'write' => [
        'host' => env('DB_WRITE_HOST', '127.0.0.1'),
    ],
    // ...
],

// Usage - automatic by Laravel
User::all(); // Reads from read replica
User::create($data); // Writes to write host
```

### 8. Query Optimization with EXPLAIN

```bash
# Check query execution plan
EXPLAIN SELECT * FROM users WHERE is_active = 1 ORDER BY created_at DESC;

# Check if index is being used
EXPLAIN FORMAT=JSON SELECT * FROM users WHERE email = 'test@example.com';
```

## 💾 CACHING STRATEGY

### 1. Cache Configuration

```env
# .env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Cache settings
CACHE_PREFIX=app_cache
APP_CACHE_TTL=3600
```

### 2. Cache Common Queries

```php
use Illuminate\Support\Facades\Cache;
use App\Services\OperationLoggerService;

class UserRepository
{
    public function __construct(private OperationLoggerService $opLogger) {}

    public function getAllUsers(int $ttl = 3600): Collection
    {
        return Cache::remember('users.all', $ttl, function () {
            $start = microtime(true);
            $users = User::all();
            $duration = (microtime(true) - $start) * 1000;

            $this->opLogger->logCacheOperation(
                operation: 'put',
                key: 'users.all',
                durationMs: $duration,
                value: serialize($users)
            );

            return $users;
        });
    }

    public function getUserById(int $id): ?User
    {
        return Cache::remember("user.{$id}", 3600, function () use ($id) {
            return User::find($id);
        });
    }

    public function invalidateUserCache(int $id): void
    {
        Cache::forget("user.{$id}");
    }
}
```

### 3. Cache Invalidation Pattern

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

// Eloquent Model with cache invalidation
class User extends Model
{
    protected static function booted(): void
    {
        static::updated(function (self $model) {
            Cache::forget("user.{$model->id}");
            Cache::forget('users.all');
        });

        static::deleted(function (self $model) {
            Cache::forget("user.{$model->id}");
            Cache::forget('users.all');
        });
    }
}
```

### 4. Cache Tags for Complex Invalidation

```php
// Cache with tags
Cache::tags(['users', 'posts'])->put('user.1.posts', $posts, 3600);

// Invalidate all user-related caches
Cache::tags(['users'])->flush();

// Selective invalidation
Cache::tags(['users', 'admin'])->flush(); // Flush both tags
```

### 5. Cache Warming

```php
// app/Console/Commands/WarmCache.php
class WarmCacheCommand extends Command
{
    public function handle()
    {
        // Warm frequently accessed data
        $this->info('Warming cache...');

        // Cache all roles
        Cache::remember('roles.all', 86400, fn() => Role::all());

        // Cache all permissions
        Cache::remember('permissions.all', 86400, fn() => Permission::all());

        // Cache system settings
        Cache::remember('settings', 86400, fn() => Setting::all());

        $this->info('Cache warmed successfully');
    }
}

// Schedule it
protected function schedule(Schedule $schedule)
{
    $schedule->command('cache:warm')->daily()->at('02:00');
}
```

### 6. Distributed Caching with Redis

```php
// cache/redis.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('CACHE_PREFIX', 'laravel_cache_'),
    ],
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
    ],
]

// Usage
Cache::store('redis')->remember('key', 3600, $callback);
```

### 7. Cache with Compression

```php
// Use compression for large cached data
class CompressedCache
{
    public static function put(string $key, $value, int $ttl = 3600): void
    {
        $compressed = gzcompress(serialize($value), 9);
        Cache::put($key, $compressed, $ttl);
    }

    public static function get(string $key)
    {
        $compressed = Cache::get($key);
        return $compressed ? unserialize(gzuncompress($compressed)) : null;
    }
}
```

### 8. Cache Metrics & Monitoring

```php
class CacheMetrics
{
    private $hits = 0;
    private $misses = 0;

    public function recordHit(string $key): void
    {
        $this->hits++;
        Log::info("Cache hit: {$key}");
    }

    public function recordMiss(string $key): void
    {
        $this->misses++;
        Log::info("Cache miss: {$key}");
    }

    public function getHitRate(): float
    {
        $total = $this->hits + $this->misses;
        return $total > 0 ? ($this->hits / $total) * 100 : 0;
    }
}
```

## 🔍 PROFILING & MONITORING

### 1. Database Profiling

```php
// Enable query logging
DB::enableQueryLog();

// Your code
$users = User::with('roles')->get();

// Get queries
$queries = DB::getQueryLog();
echo "Total queries: " . count($queries);
foreach ($queries as $query) {
    echo "Query: {$query['query']} ({$query['time']}ms)";
}
```

### 2. Memory Usage Monitoring

```php
class MemoryMonitor
{
    public static function trackMemory(callable $callback, string $label = 'Operation')
    {
        $startMem = memory_get_usage(true);
        $result = $callback();
        $endMem = memory_get_usage(true);

        $used = ($endMem - $startMem) / 1024 / 1024; // MB
        Log::info("{$label} used {$used}MB of memory");

        return $result;
    }
}

// Usage
MemoryMonitor::trackMemory(
    fn() => User::all(),
    'Load all users'
);
```

### 3. Command Line Profiling

```bash
# Check slow queries
php artisan db:monitor --max=1000

# Analyze slow logs
SHOW FULL PROCESSLIST;
SHOW ENGINE INNODB STATUS;
```

## ✅ CHECKLIST

- [ ] Enable query logging in development
- [ ] Add indexes to frequently queried columns
- [ ] Use eager loading to prevent N+1
- [ ] Implement caching for read-heavy operations
- [ ] Use pagination for large datasets
- [ ] Monitor slow queries
- [ ] Set up Redis for distributed caching
- [ ] Implement cache warming
- [ ] Schedule regular cache cleanup
- [ ] Monitor memory usage
- [ ] Use read replicas for heavy loads
