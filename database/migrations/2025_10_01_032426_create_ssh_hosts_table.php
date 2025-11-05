<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssh_hosts', function (Blueprint $table) {
            $table->id();
            $table->string('host')->unique()->comment('SSH config alias/shortname');
            $table->string('hostname')->comment('IP address or FQDN');
            $table->integer('port')->default(22);
            $table->string('user')->default('root');
            $table->string('identity_file')->nullable()->comment('Path to SSH key');
            $table->text('proxy_command')->nullable();
            $table->string('jump_host')->nullable();
            $table->json('custom_options')->nullable()->comment('Additional SSH options');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('is_reachable')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'is_reachable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssh_hosts');
    }
};
