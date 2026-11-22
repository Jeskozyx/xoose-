<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations — 10 Control Plane tables for Xoose–LoveYou Remote Service Manager.
     */
    public function up(): void
    {
        // 1. managed_apps (terhubung dengan app_projects agar selaras dengan /builder/*)
        Schema::create('managed_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_project_id')->nullable()->constrained('app_projects')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('current_version', 32)->default('1.0.0');
            $table->string('build_status', 32)->default('idle');
            $table->json('default_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. app_devices (identitas & kredensial unik per perangkat yang revocable)
        Schema::create('app_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_uuid')->unique();
            $table->foreignId('managed_app_id')->constrained('managed_apps')->cascadeOnDelete();
            $table->string('device_code', 64)->unique();
            $table->string('hostname')->default('Unknown');
            $table->string('os_info')->nullable();
            $table->string('app_version', 32)->default('1.0.0');
            $table->string('status', 24)->default('Pending')->index(); // Online, Offline, Pending, Revoked
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['managed_app_id', 'status']);
        });

        // 3. device_enrollments (kode pendaftaran perangkat dengan batas penggunaan & kedaluwarsa)
        Schema::create('device_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('managed_app_id')->constrained('managed_apps')->cascadeOnDelete();
            $table->string('enrollment_code', 64)->unique();
            $table->string('label')->nullable();
            $table->string('status', 24)->default('pending')->index(); // pending, used, expired, revoked
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('enrolled_device_id')->nullable()->constrained('app_devices')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 4. service_definitions (katalog layanan modular & allowlist aksi)
        Schema::create('service_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('service_key', 64)->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->json('supported_actions');
            $table->json('configuration_schema')->nullable();
            $table->json('permission_requirements')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        // 5. service_commands (antrean perintah remote dengan TTL, idempotency, & siklus ack)
        Schema::create('service_commands', function (Blueprint $table) {
            $table->id();
            $table->uuid('command_id')->unique();
            $table->foreignId('device_id')->constrained('app_devices')->cascadeOnDelete();
            $table->string('service_key', 64)->index();
            $table->string('action', 32);
            $table->json('payload')->nullable();
            $table->string('status', 24)->default('pending')->index(); // pending, delivered, acknowledged, succeeded, failed, expired, cancelled
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('result_message')->nullable();
            $table->json('result_payload')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'status']);
        });

        // 6. service_configs (konfigurasi runtime berversi & dukungan rollback)
        Schema::create('service_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('managed_app_id')->constrained('managed_apps')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('app_devices')->cascadeOnDelete();
            $table->string('service_key', 64)->index();
            $table->unsignedInteger('config_version');
            $table->json('config_json');
            $table->boolean('is_active')->default(true);
            $table->boolean('force_apply')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('previous_version_id')->nullable();
            $table->string('change_notes')->nullable();
            $table->timestamps();

            $table->foreign('previous_version_id')->references('id')->on('service_configs')->nullOnDelete();
            $table->index(['managed_app_id', 'device_id', 'service_key', 'is_active'], 'svc_cfg_scope_active_idx');
        });

        // 7. service_status (status terakhir tiap service pada tiap perangkat)
        Schema::create('service_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('app_devices')->cascadeOnDelete();
            $table->string('service_key', 64);
            $table->string('current_status', 24)->default('Unknown'); // Running, Stopped, Paused, Failed, Unknown
            $table->unsignedInteger('active_config_version')->default(0);
            $table->json('details')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'service_key']);
        });

        // 8. device_heartbeats (riwayat detak jantung koneksi perangkat)
        Schema::create('device_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('app_devices')->cascadeOnDelete();
            $table->string('app_version', 32)->nullable();
            $table->double('agent_uptime')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->json('services_summary')->nullable();
            $table->timestamp('received_at')->index();
            $table->timestamps();

            $table->index(['device_id', 'received_at']);
        });

        // 9. service_logs (riwayat aktivitas, error lokal, perubahan config, & audit log admin)
        Schema::create('service_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('managed_app_id')->nullable()->constrained('managed_apps')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('app_devices')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('service_key', 64)->default('system')->index();
            $table->string('level', 16)->default('info')->index(); // debug, info, warning, error, critical
            $table->string('event_type', 48)->default('agent_event')->index();
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // 10. app_releases (versi aplikasi, paket artefak, checksum SHA-256, & signature)
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('managed_app_id')->constrained('managed_apps')->cascadeOnDelete();
            $table->string('version', 32);
            $table->string('artifact_path');
            $table->string('artifact_url')->nullable();
            $table->string('sha256_checksum', 64);
            $table->string('signature', 128)->nullable();
            $table->text('release_notes')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['managed_app_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_releases');
        Schema::dropIfExists('service_logs');
        Schema::dropIfExists('device_heartbeats');
        Schema::dropIfExists('service_status');
        Schema::dropIfExists('service_configs');
        Schema::dropIfExists('service_commands');
        Schema::dropIfExists('service_definitions');
        Schema::dropIfExists('device_enrollments');
        Schema::dropIfExists('app_devices');
        Schema::dropIfExists('managed_apps');
    }
};
