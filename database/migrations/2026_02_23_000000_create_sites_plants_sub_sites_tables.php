<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('plants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->string('plant_code')->unique();
            $table->string('plant_name');
            $table->string('plant_slug')->unique();
            $table->string('status')->default('active');
            $table->text('plant_description')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('site_id');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('sub_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('plants')->onDelete('cascade');
            $table->string('sub_site_code')->unique();
            $table->string('sub_site_name');
            $table->string('sub_site_slug')->unique();
            $table->string('status')->default('active');
            $table->text('sub_site_description')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('plant_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_sites');
        Schema::dropIfExists('plants');
        Schema::dropIfExists('sites');
    }
};
