<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops and recreates cim_sql_log with Grafana-compatible fields.
     */
    public function up(): void
    {
        Schema::dropIfExists('cim_sql_log');

        Schema::create('cim_sql_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id')->index()->comment('Trace ID for Grafana correlation');
            $table->string('level', 20)->default('info')->index()->comment('info, warning, error, critical');
            $table->string('service', 100)->default('document-api')->comment('Service name for multi-service env');
            $table->string('method', 10)->nullable()->comment('HTTP method: GET, POST, PUT, DELETE');
            $table->string('url', 500)->nullable()->comment('Full request URL');
            $table->string('route', 255)->nullable()->index()->comment('Route path for Grafana label');
            $table->integer('status_code')->nullable()->index()->comment('HTTP status code');
            $table->string('function_name', 100)->nullable()->comment('Controller method name');
            $table->string('logic_name', 255)->nullable()->comment('Controller/Service class name');
            $table->json('parameters')->nullable()->comment('Request payload (sanitized)');
            $table->text('response_data')->nullable()->comment('Response body summary');
            $table->text('fail_result')->nullable()->comment('Exception message + trace on error');
            $table->timestamp('start_time')->nullable()->comment('Request start timestamp');
            $table->timestamp('end_time')->nullable()->comment('Request end timestamp');
            $table->float('duration_ms', 10, 2)->default(0)->index()->comment('Execution time in ms');
            $table->string('user_id', 100)->nullable()->index()->comment('Authenticated user ID');
            $table->string('ip_address', 45)->nullable()->comment('Client IP (v4/v6)');
            $table->string('user_agent', 255)->nullable()->comment('Client User-Agent');
            $table->boolean('is_error')->default(false)->index()->comment('Quick error filter');
            $table->string('message', 500)->nullable()->comment('Summary message');
            $table->timestamps();

            // Composite indexes for common Grafana queries
            $table->index(['level', 'created_at'], 'idx_level_created');
            $table->index(['is_error', 'created_at'], 'idx_error_created');
            $table->index(['route', 'method', 'created_at'], 'idx_route_method_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cim_sql_log');
    }
};
