<?php

namespace NetServa\Ops\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'monitoring_check_id',
        'is_active',
        'comparison_operator',
        'threshold_value',
        'severity',
        'notification_contacts',
        'alert_message_template',
        'state',
        'suppress_alerts',
        'last_evaluated_at',
        'last_alerted_at',
    ];

    protected $casts = [
        'notification_contacts' => 'array',
        'is_active' => 'boolean',
        'suppress_alerts' => 'boolean',
        'threshold_value' => 'float',
        'last_evaluated_at' => 'datetime',
        'last_alerted_at' => 'datetime',
    ];

    public function monitoringCheck(): BelongsTo
    {
        return $this->belongsTo(MonitoringCheck::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * Get the current state with color coding
     */
    public function getStateColorAttribute(): string
    {
        return match ($this->state) {
            'normal' => 'success',
            'pending' => 'info',
            'alerting' => 'danger',
            'resolved' => 'success',
            'suppressed' => 'secondary',
            default => 'secondary'
        };
    }

    /**
     * Get the severity with color coding
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            'low' => 'secondary',
            'info' => 'secondary',
            default => 'secondary'
        };
    }

    /**
     * Check if this rule is currently alerting
     */
    public function isAlerting(): bool
    {
        return $this->state === 'alerting';
    }

    /**
     * Check if this rule is suppressed
     */
    public function isSuppressed(): bool
    {
        return $this->suppress_alerts;
    }

    /**
     * Check if this rule should be evaluated
     */
    public function shouldEvaluate(): bool
    {
        return $this->is_active && ! $this->isSuppressed();
    }

    /**
     * Evaluate the rule condition
     */
    public function evaluate(float $currentValue): bool
    {
        if (! $this->threshold_value) {
            return false;
        }

        return match ($this->comparison_operator) {
            '>' => $currentValue > $this->threshold_value,
            '<' => $currentValue < $this->threshold_value,
            '>=' => $currentValue >= $this->threshold_value,
            '<=' => $currentValue <= $this->threshold_value,
            default => false,
        };
    }

    /**
     * Update rule state
     */
    public function updateState(string $newState): void
    {
        $updates = [
            'state' => $newState,
            'last_evaluated_at' => now(),
        ];

        if ($newState === 'alerting') {
            $updates['last_alerted_at'] = now();
        }

        $this->update($updates);
    }

    /**
     * Get the alert message
     */
    public function getAlertMessage(array $variables = []): string
    {
        $template = $this->alert_message_template ?: 'Alert: {rule_name} has triggered';

        return str_replace('{rule_name}', $this->name, $template);
    }

    /**
     * Suppress alerts
     */
    public function suppress(): void
    {
        $this->update(['suppress_alerts' => true]);
    }

    /**
     * Unsuppress alerts
     */
    public function unsuppress(): void
    {
        $this->update(['suppress_alerts' => false]);
    }

    /**
     * Scope for active rules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for rules ready for evaluation
     */
    public function scopeReadyForEvaluation($query)
    {
        return $query->where('is_active', true)
            ->where('suppress_alerts', false);
    }

    /**
     * Scope for rules by state
     */
    public function scopeInState($query, string $state)
    {
        return $query->where('state', $state);
    }

    /**
     * Scope for alerting rules
     */
    public function scopeAlerting($query)
    {
        return $query->where('state', 'alerting');
    }

    /**
     * Scope for rules by severity
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }
}
