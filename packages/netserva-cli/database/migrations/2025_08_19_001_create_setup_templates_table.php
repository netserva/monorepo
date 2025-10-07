<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setup_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'lamp-server', 'mail-server'
            $table->string('display_name'); // e.g., 'LAMP Server', 'Mail Server'
            $table->text('description');
            $table->string('category')->default('server'); // server, web, mail, dns, ssl
            $table->json('components'); // Array of setup components: ['host', 'web', 'db', 'ssl']
            $table->json('default_config')->nullable(); // Default configuration values
            $table->json('required_packages')->nullable(); // OS packages required
            $table->json('supported_os')->nullable(); // ['debian', 'ubuntu', 'alpine', 'arch']
            $table->text('pre_install_script')->nullable(); // Bash script to run before
            $table->text('post_install_script')->nullable(); // Bash script to run after
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_templates');
    }
};
