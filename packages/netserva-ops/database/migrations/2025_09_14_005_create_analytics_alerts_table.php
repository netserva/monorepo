<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analytics_metric_id')
                ->constrained('analytics_metrics')
                ->cascadeOnDelete();
            $table->enum('condition', ['>', '<', '=', '>=', '<=']);
            $table->decimal('threshold', 15, 4);
            $table->enum('channel', ['email', 'slack'])->default('email');
            $table->json('recipients'); // Array of email addresses or slack channels
            $table->timestamp('last_triggered_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['analytics_metric_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_alerts');
    }
};
