<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add performance indexes for common queries.
 * This optimization migration improves query performance for:
 * - Filtering active services by category
 * - Listing user's requests with status filtering
 * - Finding published form versions
 * - Preventing duplicate field keys per version
 */
return new class extends Migration
{
    public function up(): void
    {
        // Optimize services table
        Schema::table('services', function (Blueprint $table) {
            $table->index('status'); // for where status = 'active'
            $table->index('business_category_id');
            $table->index('form_template_id');
            $table->index('current_form_version_id');
        });

        // Optimize service_requests table
        Schema::table('service_requests', function (Blueprint $table) {
            // Composite index for common query: service_id + status
            $table->index(['service_id', 'status']);
            // Index for user's requests
            $table->index('requester_id');
            // Index for date range filtering and sorting
            $table->index('submitted_at');
            // Index for status filtering
            $table->index('status');
        });

        // Optimize form_template_versions table
        Schema::table('form_template_versions', function (Blueprint $table) {
            // Index for filtering draft/published versions
            $table->index('status');
            // Index for version lookup
            $table->index(['form_template_id', 'version_no']);
        });

        // Optimize form_fields table
        Schema::table('form_fields', function (Blueprint $table) {
            // Composite index for field lookup by version and key
            $table->index(['form_version_id', 'field_key']);
            // Add unique constraint to prevent duplicate field keys per version
            $table->unique(['form_version_id', 'field_key']);
        });

        // Optimize service_request_answers table
        Schema::table('service_request_answers', function (Blueprint $table) {
            // Index for field-based queries
            $table->index('field_id');
        });

        // Optimize service_request_attachments table
        Schema::table('service_request_attachments', function (Blueprint $table) {
            // Index for finding request attachments
            $table->index('request_id');
            // Index for field-specific attachments
            $table->index('field_id');
        });

        // Optimize service_categories table
        Schema::table('service_categories', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('parent_id');
        });

        // Optimize form_templates table
        Schema::table('form_templates', function (Blueprint $table) {
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['business_category_id']);
            $table->dropIndex(['form_template_id']);
            $table->dropIndex(['current_form_version_id']);
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropIndex(['service_id', 'status']);
            $table->dropIndex(['requester_id']);
            $table->dropIndex(['submitted_at']);
            $table->dropIndex(['status']);
        });

        Schema::table('form_template_versions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['form_template_id', 'version_no']);
        });

        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropIndex(['form_version_id', 'field_key']);
            $table->dropUnique(['form_version_id', 'field_key']);
        });

        Schema::table('service_request_answers', function (Blueprint $table) {
            $table->dropIndex(['field_id']);
        });

        Schema::table('service_request_attachments', function (Blueprint $table) {
            $table->dropIndex(['request_id']);
            $table->dropIndex(['field_id']);
        });

        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['parent_id']);
        });

        Schema::table('form_templates', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });
    }
};
