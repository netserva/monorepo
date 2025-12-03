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
        Schema::create('domain_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_registrar_id')->constrained('domain_registrars')->onDelete('cascade');
            $table->foreignId('infrastructure_node_id')->nullable()->constrained('infrastructure_nodes')->onDelete('set null');
            $table->string('domain_name')->unique();
            $table->date('registration_date');
            $table->date('expiry_date');
            $table->date('renewal_date')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->json('registrant_contact')->nullable();
            $table->json('nameservers')->nullable();
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('status', ['active', 'inactive', 'expired', 'suspended'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_registrations');
    }
};
