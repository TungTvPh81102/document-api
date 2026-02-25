<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Action Logs Table - ghi log mọi hành động kinh doanh
        Schema::create('action_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id', 100)->nullable()->index();
            $table->string('model_class', 255)->nullable();
            $table->string('model_id', 100)->nullable();
            $table->string('model_name', 100)->nullable();
            $table->string('action', 50)->index(); // create, update, delete, restore
            $table->string('source', 100)->nullable(); // controller, service, command
            $table->json('changes')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('timestamp')->index();

            $table->index(['user_id', 'timestamp']);
            $table->index(['model_class', 'model_id']);
            $table->index(['action', 'timestamp']);
        });

        // Auth Logs Table - ghi log sự kiện xác thực
        Schema::create('auth_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id', 100)->nullable()->index();
            $table->string('action', 50)->index(); // login, logout, failed_login, lock, unlock
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('timestamp')->index();

            $table->index(['user_id', 'timestamp']);
            $table->index(['action', 'timestamp']);
        });

        // Security Events Table - ghi log sự kiện bảo mật
        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id', 100)->nullable()->index();
            $table->string('event_type', 100)->index(); // suspicious_access, permission_denied, rate_limit
            $table->text('description')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('timestamp')->index();

            $table->index(['event_type', 'timestamp']);
            $table->index(['user_id', 'timestamp']);
        });

        // Operation Logs Table - ghi log các truy vấn DB, cache operations, API calls
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('operation', 50)->index(); // query, insert, update, delete, api_call, cache_op
            $table->string('type', 50)->index(); // query, database_op, cache_op, external_api
            $table->string('query', 1000)->nullable();
            $table->json('bindings')->nullable();
            $table->string('table', 100)->nullable();
            $table->string('target_id', 100)->nullable();
            $table->float('duration_ms', 10, 2)->default(0)->index();
            $table->boolean('is_slow')->default(false)->index();
            $table->integer('rows_affected')->nullable();
            $table->string('cache_key', 255)->nullable();
            $table->boolean('hit')->nullable();
            $table->integer('value_size')->nullable();
            $table->string('method', 10)->nullable(); // HTTP method cho API calls
            $table->string('url', 500)->nullable();
            $table->integer('status_code')->nullable();
            $table->string('connection', 50)->nullable();
            $table->string('user_id', 100)->nullable()->index();
            $table->text('error')->nullable();
            $table->timestamp('timestamp')->index();

            $table->index(['operation', 'timestamp']);
            $table->index(['is_slow', 'timestamp']);
            $table->index(['type', 'is_slow', 'timestamp']);
            $table->index(['user_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_logs');
        Schema::dropIfExists('auth_logs');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('operation_logs');
    }
};
