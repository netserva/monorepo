<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ip_network_id')->constrained()->cascadeOnDelete();
            $table->string('start_ip', 45); // Start of reserved range
            $table->string('end_ip', 45); // End of reserved range
            $table->string('name'); // Reservation name
            $table->text('description')->nullable();

            // Reservation details
            $table->enum('reservation_type', [
                'static_range',
                'dhcp_pool',
                'dhcp_exclusion',
                'network_infrastructure',
                'future_allocation',
                'maintenance',
                'security_buffer',
            ]);

            $table->string('purpose')->nullable(); // Purpose of reservation
            $table->string('contact')->nullable(); // Contact person
            $table->string('project')->nullable(); // Associated project

            // Lifecycle management
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable(); // Reservation start date
            $table->date('valid_until')->nullable(); // Reservation end date
            $table->integer('address_count')->default(0); // Number of addresses reserved

            // Auto-allocation settings
            $table->boolean('allow_auto_allocation')->default(false); // Allow automatic IP allocation from this range
            $table->json('allocation_rules')->nullable(); // Rules for automatic allocation

            // Metadata
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index(['reservation_type', 'is_active']);
            $table->index(['valid_from', 'valid_until']);
            $table->index(['allow_auto_allocation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_reservations');
    }
};
