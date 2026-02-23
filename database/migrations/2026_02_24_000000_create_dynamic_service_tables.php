<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Service Categories
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('service_categories')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Form Templates
        Schema::create('form_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('template_category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Services
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('svc_code')->unique();
            $table->foreignId('business_category_id')->constrained('service_categories');
            $table->foreignId('form_template_id')->constrained('form_templates');
            $table->string('status')->default('draft'); // draft, active, retired
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        // 4. Service Translations
        Schema::create('service_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->string('locale')->index(); // zh, zh_tw, en, vi
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['service_id', 'locale']);
        });

        // 5. Form Template Versions
        Schema::create('form_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_template_id')->constrained('form_templates')->onDelete('cascade');
            $table->integer('version_no');
            $table->jsonb('schema_json');
            $table->string('status')->default('draft'); // draft, published, archived
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['form_template_id', 'version_no']);
        });

        // Update services table to include the current version (self-referencing logic handled in code)
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('current_form_version_id')->nullable()->constrained('form_template_versions');
        });

        // 6. Form Fields
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_version_id')->constrained('form_template_versions')->onDelete('cascade');
            $table->string('field_key');
            $table->string('label_default');
            $table->string('field_type'); // short_text, long_text, single_choice, multi_choice, file, date, etc.
            $table->boolean('is_required')->default(false);
            $table->integer('display_order')->default(0);
            $table->string('options_source')->default('inline'); // inline, master_data, api
            $table->jsonb('options_json')->nullable();
            $table->jsonb('validation_json')->nullable();
            $table->jsonb('visibility_rule_json')->nullable();
            $table->timestamps();
        });

        // 7. Form Field Translations
        Schema::create('form_field_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('form_fields')->onDelete('cascade');
            $table->string('locale')->index();
            $table->string('label');
            $table->string('placeholder')->nullable();
            $table->string('help_text')->nullable();
            $table->timestamps();
            $table->unique(['field_id', 'locale']);
        });

        // 8. Service Requests
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('form_version_id')->constrained('form_template_versions');
            $table->string('requester_id')->index(); // Linked to model or external ID
            $table->string('status')->default('submitted'); // submitted, in_progress, approved, rejected, closed
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        // 9. Service Request Answers
        Schema::create('service_request_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('service_requests')->onDelete('cascade');
            $table->foreignId('field_id')->constrained('form_fields')->onDelete('cascade');
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 15, 2)->nullable();
            $table->date('value_date')->nullable();
            $table->jsonb('value_json')->nullable();
            $table->timestamps();
            $table->unique(['request_id', 'field_id']);
        });

        // 10. Service Request Attachments
        Schema::create('service_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('service_requests')->onDelete('cascade');
            $table->foreignId('field_id')->nullable()->constrained('form_fields')->onDelete('SET NULL');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->bigInteger('file_size');
            $table->string('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_attachments');
        Schema::dropIfExists('service_request_answers');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('form_field_translations');
        Schema::dropIfExists('form_fields');
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['current_form_version_id']);
            $table->dropColumn('current_form_version_id');
        });
        Schema::dropIfExists('form_template_versions');
        Schema::dropIfExists('service_translations');
        Schema::dropIfExists('services');
        Schema::dropIfExists('form_templates');
        Schema::dropIfExists('service_categories');
    }
};
