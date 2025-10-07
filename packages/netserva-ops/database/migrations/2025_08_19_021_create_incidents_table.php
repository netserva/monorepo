<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();

            // Incident identification
            $table->string('incident_number')->unique(); // INC-2025-001
            $table->string('title');
            $table->text('description')->nullable();

            // Source information
            $table->foreignId('alert_rule_id')
                ->nullable()
                ->constrained('alert_rules')
                ->nullOnDelete();
            $table->foreignId('monitoring_check_id')
                ->nullable()
                ->constrained('monitoring_checks')
                ->nullOnDelete();
            $table->foreignId('infrastructure_node_id')
                ->nullable()
                ->constrained('infrastructure_nodes')
                ->nullOnDelete();

            // Incident classification
            $table->enum('incident_type', [
                'outage', 'degradation', 'maintenance', 'security', 'capacity', 'other',
            ])->default('outage');
            $table->enum('severity', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->enum('priority', ['p1', 'p2', 'p3', 'p4', 'p5'])->default('p3');
            $table->enum('category', [
                'infrastructure', 'application', 'network', 'security', 'data', 'user_error', 'external',
            ])->nullable();

            // Status tracking
            $table->enum('status', [
                'open', 'investigating', 'identified', 'monitoring', 'resolved', 'closed', 'cancelled',
            ])->default('open');
            $table->timestamp('detected_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('investigating_started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Impact assessment
            $table->text('impact_description')->nullable();
            $table->json('affected_services')->nullable(); // List of affected services
            $table->json('affected_customers')->nullable(); // Customer count/segments affected
            $table->integer('estimated_affected_users')->nullable();
            $table->decimal('business_impact_score', 5, 2)->nullable(); // 0-100
            $table->text('business_impact_description')->nullable();

            // Time tracking
            $table->integer('detection_time_minutes')->nullable(); // Time to detect
            $table->integer('response_time_minutes')->nullable(); // Time to first response
            $table->integer('acknowledgment_time_minutes')->nullable(); // Time to acknowledge
            $table->integer('resolution_time_minutes')->nullable(); // Time to resolve
            $table->integer('total_downtime_minutes')->nullable();

            // Assignment and ownership
            $table->string('assigned_to')->nullable(); // Primary assignee
            $table->json('assigned_team')->nullable(); // Team or escalation group
            $table->string('incident_commander')->nullable(); // For major incidents
            $table->json('participants')->nullable(); // Everyone involved

            // Communication
            $table->boolean('customer_notification_sent')->default(false);
            $table->timestamp('customer_notification_at')->nullable();
            $table->text('customer_message')->nullable();
            $table->json('notification_channels_used')->nullable();
            $table->boolean('status_page_updated')->default(false);
            $table->text('status_page_message')->nullable();

            // Root cause analysis
            $table->text('root_cause')->nullable();
            $table->enum('root_cause_category', [
                'code_bug', 'configuration_error', 'infrastructure_failure',
                'capacity_issue', 'human_error', 'third_party', 'unknown',
            ])->nullable();
            $table->text('contributing_factors')->nullable();
            $table->json('timeline')->nullable(); // Detailed incident timeline

            // Resolution details
            $table->text('resolution_summary')->nullable();
            $table->json('resolution_steps')->nullable(); // Steps taken to resolve
            $table->text('temporary_fix')->nullable();
            $table->text('permanent_fix')->nullable();
            $table->boolean('requires_follow_up')->default(false);

            // Prevention and improvement
            $table->json('lessons_learned')->nullable();
            $table->json('action_items')->nullable(); // Follow-up tasks
            $table->json('preventive_measures')->nullable(); // Measures to prevent recurrence
            $table->decimal('prevention_confidence', 3, 2)->nullable(); // 0-1, confidence in prevention

            // Cost analysis
            $table->decimal('estimated_cost', 12, 2)->nullable(); // Financial impact
            $table->string('cost_currency', 3)->default('USD');
            $table->text('cost_breakdown')->nullable();
            $table->integer('engineering_hours_spent')->nullable();

            // Escalation tracking
            $table->boolean('escalated')->default(false);
            $table->timestamp('escalated_at')->nullable();
            $table->string('escalated_to')->nullable();
            $table->text('escalation_reason')->nullable();
            $table->enum('escalation_level', ['l1', 'l2', 'l3', 'executive'])->nullable();

            // External factors
            $table->json('external_dependencies')->nullable(); // External services involved
            $table->json('third_party_providers')->nullable(); // Third-party services affected
            $table->boolean('caused_by_external_service')->default(false);
            $table->text('external_service_details')->nullable();

            // Metrics and data
            $table->json('key_metrics')->nullable(); // Important metrics during incident
            $table->json('error_rates')->nullable();
            $table->json('performance_metrics')->nullable();
            $table->json('system_state_snapshot')->nullable(); // System state at time of incident

            // Documentation and evidence
            $table->json('log_excerpts')->nullable(); // Important log entries
            $table->json('screenshots')->nullable(); // Screenshot URLs
            $table->json('graphs_charts')->nullable(); // Chart/graph URLs
            $table->json('evidence_files')->nullable(); // Supporting files

            // Quality assurance
            $table->boolean('post_mortem_required')->default(false);
            $table->boolean('post_mortem_completed')->default(false);
            $table->timestamp('post_mortem_due_at')->nullable();
            $table->string('post_mortem_document_url')->nullable();
            $table->enum('incident_review_status', ['pending', 'completed', 'not_required'])->default('pending');

            // Recurring incident tracking
            $table->boolean('is_recurring')->default(false);
            $table->foreignId('parent_incident_id')->nullable()->constrained('incidents');
            $table->json('related_incident_ids')->nullable();
            $table->integer('recurrence_count')->default(0);
            $table->timestamp('last_occurrence_at')->nullable();

            // Change management
            $table->json('related_changes')->nullable(); // Recent changes that might be related
            $table->boolean('caused_by_change')->default(false);
            $table->string('change_id')->nullable();
            $table->text('change_details')->nullable();

            // Monitoring and alerting effectiveness
            $table->enum('detection_method', ['automated', 'manual', 'customer_report', 'third_party'])
                ->default('automated');
            $table->text('detection_details')->nullable();
            $table->boolean('monitoring_gaps_identified')->default(false);
            $table->json('monitoring_improvements')->nullable();

            // Tags and metadata
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('metadata')->nullable();

            // Audit information
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('closed_by')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['status', 'severity']);
            $table->index(['incident_type', 'status']);
            $table->index(['detected_at', 'severity']);
            $table->index(['assigned_to', 'status']);
            $table->index(['alert_rule_id']);
            $table->index(['monitoring_check_id']);
            $table->index(['infrastructure_node_id']);
            $table->index(['is_recurring', 'parent_incident_id']);
            $table->index(['created_at', 'status']);
            $table->index(['resolution_time_minutes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
