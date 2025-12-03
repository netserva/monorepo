<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SSH Hosts (connection configurations)
        Schema::create('ssh_hosts', function (Blueprint $table) {
            $table->id();
            $table->string('host')->unique();
            $table->string('hostname');
            $table->integer('port')->default(22);
            $table->string('user')->default('root');
            $table->string('identity_file')->nullable();
            $table->text('proxy_command')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // SSH Keys
        Schema::create('ssh_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('public_key');
            $table->text('private_key')->nullable();
            $table->string('fingerprint')->nullable();
            $table->string('type')->default('rsa');
            $table->integer('bits')->default(4096);
            $table->string('comment')->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['name', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssh_keys');
        Schema::dropIfExists('ssh_hosts');
    }
};
