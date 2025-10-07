<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();

            // Rule identification
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Rule source
            $table->enum('rule_type', ['check', 'metric', 'composite', 'anomaly', 'threshold', 'pattern']);
            $table->foreignId('monitoring_check_id')
                ->nullable()
                ->constrained('monitoring_checks')
                ->cascadeOnDelete();

            // Rule conditions
            $table->boolean('is_active')->default(true);
            $table->json('conditions'); // Complex rule conditions
            $table->enum('condition_logic', ['all', 'any', 'custom'])->default('all');
            $table->text('custom_logic_expression')->nullable(); // e.g., "(A && B) || C"

            // Threshold configuration
            $table->string('metric_name')->nullable();
            $table->enum('comparison_operator', ['>', '<', '>=', '<=', '==', '!=', 'between', 'not_between'])
                ->nullable();
            $table->decimal('threshold_value', 20, 6)->nullable();
            $table->decimal('threshold_min', 20, 6)->nullable(); // For between operator
            $table->decimal('threshold_max', 20, 6)->nullable(); // For between operator
            $table->string('threshold_unit')->nullable();

            // Time window configuration
            $table->integer('evaluation_window_minutes')->default(5);
            $table->integer('datapoints_required')->default(1);
            $table->enum('aggregation_method', ['min', 'max', 'avg', 'sum', 'count', 'p50', 'p95', 'p99'])
                ->default('avg');
            $table->integer('missing_data_points_as')->nullable(); // Treat missing data as this value

            // Alert configuration
            $table->enum('severity', ['critical', 'high', 'medium', 'low', 'info'])->default('medium');
            $table->integer('alert_delay_minutes')->default(0); // Wait before alerting
            $table->integer('repeat_interval_minutes')->nullable(); // Repeat alert if not resolved
            $table->integer('max_alerts_per_hour')->nullable(); // Rate limiting
            $table->boolean('auto_resolve')->default(true);
            $table->integer('auto_resolve_after_minutes')->nullable();

            // Notification configuration
            $table->json('notification_channels'); // Which channels to use
            $table->json('notification_contacts')->nullable(); // Specific contacts
            $table->text('alert_title_template')->nullable();
            $table->text('alert_message_template')->nullable();
            $table->text('recovery_message_template')->nullable();
            $table->json('notification_metadata')->nullable(); // Additional data for notifications

            // Escalation configuration
            $table->boolean('enable_escalation')->default(false);
            $table->json('escalation_rules')->nullable(); // After X minutes, notify Y
            $table->integer('escalation_level')->default(0); // Current escalation level
            $table->timestamp('escalation_started_at')->nullable();

            // Rule state
            $table->enum('state', ['normal', 'pending', 'alerting', 'resolved', 'suppressed'])
                ->default('normal');
            $table->timestamp('state_changed_at')->nullable();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamp('last_alerted_at')->nullable();
            $table->integer('consecutive_breaches')->default(0);
            $table->json('current_values')->nullable(); // Current metric values
            $table->text('state_reason')->nullable();

            // Statistics
            $table->integer('total_evaluations')->default(0);
            $table->integer('total_breaches')->default(0);
            $table->integer('total_alerts_sent')->default(0);
            $table->integer('false_positives')->default(0);
            $table->decimal('breach_percentage', 5, 2)->default(0);
            $table->integer('average_resolution_time_minutes')->nullable();

            // Suppression and maintenance
            $table->boolean('suppress_alerts')->default(false);
            $table->timestamp('suppression_start_at')->nullable();
            $table->timestamp('suppression_end_at')->nullable();
            $table->text('suppression_reason')->nullable();
            $table->json('suppression_schedule')->nullable(); // Recurring suppression windows

            // Dependencies and grouping
            $table->json('depends_on_rule_ids')->nullable();
            $table->string('rule_group')->nullable();
            $table->json('tags')->nullable();
            $table->integer('priority')->default(50);

            // Advanced features
            $table->boolean('use_machine_learning')->default(false);
            $table->json('ml_config')->nullable(); // ML model configuration
            $table->decimal('anomaly_threshold', 5, 2)->nullable(); // 0-100
            $table->json('baseline_config')->nullable(); // Baseline calculation settings
            $table->json('seasonal_config')->nullable(); // Seasonal pattern detection

            // Actions
            $table->json('automated_actions')->nullable(); // Actions to take when alert triggers
            $table->json('runbook_url')->nullable(); // Link to runbook
            $table->json('dashboard_url')->nullable(); // Link to relevant dashboard

            // Metadata
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();
            $table->string('modified_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'state']);
            $table->index(['monitoring_check_id', 'is_active']);
            $table->index(['rule_type', 'is_active']);
            $table->index(['severity', 'state']);
            $table->index(['last_evaluated_at']);
            $table->index(['suppress_alerts', 'suppression_end_at']);
            $table->index(['rule_group', 'is_active']);
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
