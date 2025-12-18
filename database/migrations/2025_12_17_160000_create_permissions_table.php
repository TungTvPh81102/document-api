<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('resource')->comment('Resource name (e.g., documents, categories)');
            $table->string('action')->comment('Action type (view, create, edit, delete)');
            $table->unsignedBigInteger('resource_id')->nullable()->comment('Specific resource ID if applicable');
            $table->timestamps();

            $table->index(['user_id', 'resource', 'action']);
            $table->unique(['user_id', 'resource', 'action', 'resource_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
