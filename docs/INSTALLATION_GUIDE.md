# 📦 INSTALLATION & SETUP GUIDE

Các bước để kích hoạt hệ thống logging tối ưu mới:

## 1️⃣ INSTALL NEW PACKAGES (if needed)

```bash
# Composer dependencies (already in your project)
composer update

# Ensure you have Redis for queuing (recommended)
# Ubuntu/Debian:
sudo apt-get install redis-server

# Or use SQLite queue driver as fallback
```

## 2️⃣ PUBLISH CONFIGURATION

```bash
# Publish the enhanced logging configuration
php artisan config:publish logging-enhanced

# Or manually copy:
cp config/logging-enhanced.php config/logging-enhanced.php
```

## 3️⃣ CREATE ENVIRONMENT VARIABLES

Add to your `.env` file:

```env
# ========================================
# LOGGING CONFIGURATION
# ========================================

# Async Logging (recommended for production)
LOG_REQUEST_ASYNC=true
LOG_ACTION_ASYNC=true
LOG_OPERATION_ASYNC=true

# Path filtering
LOG_SKIP_PATHS_ENABLED=true

# Performance thresholds (milliseconds)
LOG_SLOW_QUERY_THRESHOLD=1000
LOG_SLOW_CACHE_THRESHOLD=100

# Retention policy (days)
LOG_REQUEST_RETENTION_DAYS=90
LOG_ACTION_RETENTION_DAYS=180
LOG_OPERATION_RETENTION_DAYS=60
LOG_SECURITY_RETENTION_DAYS=365

# Queue configuration
LOG_QUEUE_NAME=logs
LOG_BATCH_SIZE=100
LOG_BATCH_TIMEOUT=5

# Performance monitoring
LOG_SLOW_QUERIES=true
LOG_CACHE_OPERATIONS=true
LOG_API_CALLS=true

# Security logging
LOG_SECURITY_EVENTS=true
LOG_PERMISSION_CHANGES=true
LOG_ACCESS_DENIED=true
LOG_FAILED_LOGINS=true

# ========================================
# OPTIONAL: REDIS CACHE (for better performance)
# ========================================
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## 4️⃣ RUN DATABASE MIGRATIONS

```bash
# Create new logging tables
php artisan migrate

# If you need to rollback:
php artisan migrate:rollback
```

## 5️⃣ UPDATE SERVICE PROVIDERS

Register the LoggerServiceProvider in `config/app.php`:

```php
'providers' => [
    // ... existing providers
    App\Providers\LoggerServiceProvider::class,
],
```

**Or** in `bootstrap/providers.php` (Laravel 11+):

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\LoggerServiceProvider::class,
];
```

## 6️⃣ START QUEUE WORKER

Queue worker processes async logs in the background:

```bash
# Start queue worker (development)
php artisan queue:work --queue=logs

# Start multiple workers (production)
php artisan queue:work --queue=logs --max-jobs=1000
php artisan queue:work --queue=logs --max-jobs=1000

# Use supervisor for production:
# See: https://laravel.com/docs/queues#supervisor-configuration
```

## 7️⃣ SCHEDULE COMMANDS

Add commands to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Archive logs weekly (older than 90 days)
    $schedule->command('logs:archive')
        ->weekly();

    // Cleanup old logs daily at 3:00 AM
    $schedule->command('logs:cleanup')
        ->daily()
        ->at('03:00');

    // Generate analytics report daily at 11:00 PM
    $schedule->command('logs:analytics --days=1')
        ->daily()
        ->at('23:00')
        ->onOneServer();
}
```

## 8️⃣ VERIFY INSTALLATION

Test that everything is working:

```bash
# Check config is published
php artisan config:show logging-enhanced

# Run migrations
php artisan migrate --list | grep enhanced

# Test analytics command
php artisan logs:analytics

# Check queue is working
php artisan queue:work --queue=logs --verbose

# Make a test API request and check logs
curl http://localhost:8000/api/health
```

## 9️⃣ OPTIONAL: SETUP GRAFANA INTEGRATION

If using Grafana/Loki for log visualization:

### Install Promtail

```bash
cd /opt
sudo wget https://github.com/grafana/loki/releases/download/v2.9.3/promtail-linux-amd64.zip
sudo unzip promtail-linux-amd64.zip
sudo mv promtail-linux-amd64 /usr/local/bin/promtail
```

### Configure Promtail

```bash
sudo tee /etc/promtail/config.yml > /dev/null <<EOF
clients:
  - url: http://loki:3100/api/prom/push

scrape_configs:
  - job_name: document-api
    static_configs:
      - targets:
          - localhost
        labels:
          job: document-api
          env: production
          __path__: /var/www/html/storage/logs/*.log
EOF
```

### Start Promtail

```bash
sudo systemctl restart promtail
```

## 🔟 PRODUCTION DEPLOYMENT

For production environments:

### 1. Use Redis for Queue

```bash
# Install Redis
sudo apt-get install redis-server

# Verify Redis
redis-cli ping
# Output: PONG
```

### 2. Setup Supervisor for Queue Worker

```bash
sudo tee /etc/supervisor/conf.d/document-api-logs.conf > /dev/null <<EOF
[program:document-api-logs]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --queue=logs --max-jobs=1000
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/document-api-logs.log
stopwaitsecs=3600
EOF

# Update supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all

# Monitor
sudo supervisorctl status
```

### 3. Setup Log Rotation

```bash
sudo tee /etc/logrotate.d/document-api > /dev/null <<EOF
/var/www/html/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        /bin/kill -TERM \$(/var/run/document-api.pid) 2>/dev/null || true
    endscript
}
EOF
```

### 4. Setup Cron for Scheduling

```bash
# Run Laravel scheduler every minute
*/1 * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

## ✅ QUICK CHECKLIST

- [ ] `.env` configured with logging variables
- [ ] Database migrations run
- [ ] LoggerServiceProvider registered
- [ ] Queue worker started
- [ ] Schedule commands configured
- [ ] Test API request successful
- [ ] Logs appearing in database
- [ ] Analytics command working
- [ ] Cleanup scheduled

## 🧪 TESTING

### Test Logging

```bash
# Start queue worker in terminal 1
php artisan queue:work --queue=logs --verbose

# Make request in terminal 2
curl http://localhost:8000/api/users

# Check database in terminal 3
php artisan tinker
>>> DB::table('cim_sql_log')->latest()->first();
```

### Test Action Logging

```php
// In your controller
use App\Services\ActionLoggerService;

$actionLogger = app(ActionLoggerService::class);
$actionLogger->logAction('test_action', 'TestController', 'This is a test');

// Check database
DB::table('action_logs')->latest()->first();
```

### Test Analytics

```bash
php artisan logs:analytics

# Expected output:
# === LOG ANALYTICS (Last 7 days) ===
# REQUEST STATISTICS:
#   Total requests: XXXX
#   By method: GET, POST, etc.
```

## 🐛 TROUBLESHOOTING

### Logs not appearing in database

```bash
# 1. Check queue is running
ps aux | grep queue:work

# 2. Check queue is not stuck
redis-cli INFO | grep connected_clients

# 3. Check error logs
tail -f storage/logs/laravel.log

# 4. Check failed jobs
php artisan queue:failed

# 5. Retry failed jobs
php artisan queue:retry all
```

### High memory usage

```bash
# Reduce batch size
export LOG_BATCH_SIZE=50

# Enable compression
export LOG_OPERATION_COMPRESSION=true

# Archive old logs
php artisan logs:archive --days=30
```

### Slow performance

```bash
# Check slow queries
php artisan logs:analytics

# Check if async logging is enabled
cat .env | grep LOG_REQUEST_ASYNC

# Enable it if not
echo "LOG_REQUEST_ASYNC=true" >> .env
```

## 📞 SUPPORT

If you encounter issues:

1. Check [LOGGING_GUIDE.md](./LOGGING_GUIDE.md)
2. Check [DATABASE_OPTIMIZATION.md](./DATABASE_OPTIMIZATION.md)
3. Check [PROJECT_OPTIMIZATION.md](./PROJECT_OPTIMIZATION.md)
4. Review `storage/logs/laravel.log`
5. Run `php artisan logs:analytics`

---

**Installation Time:** ~5-10 minutes  
**Status:** ✅ Production Ready
