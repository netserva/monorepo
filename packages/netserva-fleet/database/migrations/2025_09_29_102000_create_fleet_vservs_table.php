<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vservs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vhost_id')->constrained('fleet_vhosts')->onDelete('cascade');
            $table->string('name'); // 'nginx', 'mariadb', 'postfix', 'redis'
            $table->string('slug');
            $table->string('category')->nullable(); // 'web', 'database', 'mail', 'dns', 'cache'
            $table->string('version')->nullable(); // '1.24.0', '10.11', '3.8.1'
            $table->integer('port')->nullable(); // Primary service port
            $table->json('additional_ports')->nullable(); // Array of additional ports
            $table->string('config_path')->nullable(); // '/etc/nginx/nginx.conf'
            $table->string('data_path')->nullable(); // '/var/lib/mysql'
            $table->string('log_path')->nullable(); // '/var/log/nginx'
            $table->string('systemd_unit')->nullable(); // 'nginx.service'
            $table->enum('status', ['running', 'stopped', 'failed', 'unknown'])->default('unknown');
            $table->boolean('auto_start')->default(true);
            $table->json('dependencies')->nullable(); // Array of dependent vservs
            $table->json('config')->nullable(); // Service-specific configuration
            $table->string('health_check_url')->nullable();
            $table->timestamp('last_health_check')->nullable();
            $table->enum('health_status', ['healthy', 'degraded', 'unhealthy', 'unknown'])->default('unknown');
            $table->json('resource_usage')->nullable(); // CPU, memory, disk usage
            $table->json('metadata')->nullable(); // Additional service-specific data
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for common queries
            $table->index(['vhost_id', 'status']);
            $table->index(['vhost_id', 'category']);
            $table->index(['category', 'status']);
            $table->index(['port']);
            $table->index(['health_status', 'is_active']);

            // Unique constraint for vhost_id + name combination
            $table->unique(['vhost_id', 'name'], 'fleet_vservs_vhost_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vservs');
    }
};
