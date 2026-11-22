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
        Schema::create('app_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('version')->default('1.0.0');
            $table->string('source_path');
            $table->string('manifest_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('app_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_template_id')->constrained('app_templates')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('draft'); // draft, ready, building, built, failed, archived
            $table->json('features')->nullable();
            $table->json('configuration')->nullable();
            $table->string('workspace_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('app_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_project_id')->constrained('app_projects')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->json('features')->nullable();
            $table->json('configuration')->nullable();
            $table->string('change_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['app_project_id', 'revision_number']);
        });

        Schema::create('app_builds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_project_id')->constrained('app_projects')->cascadeOnDelete();
            $table->foreignId('app_revision_id')->nullable()->constrained('app_revisions')->nullOnDelete();
            $table->string('status')->default('queued'); // queued, preparing, building, succeeded, failed, cancelled
            $table->string('format')->default('onedir'); // onedir, onefile
            $table->string('engine_version')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('exit_code')->nullable();
            $table->string('artifact_path')->nullable();
            $table->string('log_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_builds');
        Schema::dropIfExists('app_revisions');
        Schema::dropIfExists('app_projects');
        Schema::dropIfExists('app_templates');
    }
};
