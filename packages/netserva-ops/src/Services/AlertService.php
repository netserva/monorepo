<?php

namespace NetServa\Ops\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use NetServa\Ops\Models\AnalyticsAlert;
use NetServa\Ops\Models\AnalyticsMetric;

class AlertService
{
    /**
     * Create a new alert
     */
    public function create(array $config): AnalyticsAlert
    {
        return AnalyticsAlert::create([
            'analytics_metric_id' => $config['analytics_metric_id'],
            'condition' => $config['condition'],
            'threshold' => $config['threshold'],
            'channel' => $config['channel'] ?? 'email',
            'recipients' => $config['recipients'],
            'is_active' => $config['is_active'] ?? true,
        ]);
    }

    /**
     * Update an alert
     */
    public function update(int $alertId, array $data): AnalyticsAlert
    {
        $alert = AnalyticsAlert::findOrFail($alertId);
        $alert->update($data);

        return $alert;
    }

    /**
     * Delete an alert
     */
    public function delete(int $alertId): bool
    {
        return AnalyticsAlert::findOrFail($alertId)->delete();
    }

    /**
     * Check all active alerts
     */
    public function checkAll(): array
    {
        $alerts = AnalyticsAlert::where('is_active', true)
            ->with('metric')
            ->get();

        $results = ['triggered' => 0, 'total' => 0];

        foreach ($alerts as $alert) {
            $results['total']++;

            if ($this->checkAlert($alert)) {
                $results['triggered']++;
            }
        }

        return $results;
    }

    /**
     * Check a specific alert
     */
    public function checkAlert(AnalyticsAlert $alert): bool
    {
        $metric = $alert->metric;

        if (! $metric || ! $metric->is_active || $metric->value === null) {
            return false;
        }

        $triggered = $this->evaluateConditionPrivate(
            $metric->value,
            $alert->condition,
            $alert->threshold
        );

        if ($triggered) {
            $this->triggerAlert($alert, $metric);

            return true;
        }

        return false;
    }

    /**
     * Evaluate a condition for an alert (public method for testing)
     */
    public function evaluateCondition(AnalyticsAlert $alert): bool
    {
        $metric = $alert->metric;

        if (! $metric || ! $metric->is_active || $metric->value === null) {
            return false;
        }

        return match ($alert->condition) {
            '>' => $metric->value > $alert->threshold,
            '<' => $metric->value < $alert->threshold,
            '>=' => $metric->value >= $alert->threshold,
            '<=' => $metric->value <= $alert->threshold,
            '=' => abs($metric->value - $alert->threshold) < 0.01,
            default => false
        };
    }

    /**
     * Evaluate alert condition (private helper)
     */
    private function evaluateConditionPrivate(float $value, string $condition, float $threshold): bool
    {
        return match ($condition) {
            '>' => $value > $threshold,
            '<' => $value < $threshold,
            '>=' => $value >= $threshold,
            '<=' => $value <= $threshold,
            '=' => abs($value - $threshold) < 0.01,
            default => false
        };
    }

    /**
     * Trigger an alert notification
     */
    private function triggerAlert(AnalyticsAlert $alert, AnalyticsMetric $metric): void
    {
        $message = "Alert: {$metric->name} is {$metric->value} {$metric->unit} ".
                  "(threshold: {$alert->condition} {$alert->threshold})";

        match ($alert->channel) {
            'email' => $this->sendEmailAlert($alert, $message),
            'slack' => $this->sendSlackAlert($alert, $message),
            default => null
        };

        // Update last triggered time
        $alert->update(['last_triggered_at' => now()]);
    }

    /**
     * Send email alert
     */
    private function sendEmailAlert(AnalyticsAlert $alert, string $message): void
    {
        foreach ($alert->recipients as $recipient) {
            try {
                Mail::raw($message, function ($mail) use ($recipient) {
                    $mail->to($recipient)
                        ->subject('Analytics Alert');
                });
            } catch (\Exception $e) {
                // Log error but continue
                \Log::error("Failed to send email alert: {$e->getMessage()}");
            }
        }
    }

    /**
     * Send Slack alert
     */
    private function sendSlackAlert(AnalyticsAlert $alert, string $message): void
    {
        foreach ($alert->recipients as $webhookUrl) {
            try {
                Http::post($webhookUrl, [
                    'text' => $message,
                ]);
            } catch (\Exception $e) {
                // Log error but continue
                \Log::error("Failed to send Slack alert: {$e->getMessage()}");
            }
        }
    }

    /**
     * Get all active alerts
     */
    public function getActive(): Collection
    {
        return AnalyticsAlert::where('is_active', true)
            ->with('metric')
            ->get();
    }

    /**
     * Get alerts by severity (mapped from condition type)
     */
    public function getBySeverity(string $severity): Collection
    {
        $conditions = match ($severity) {
            'critical' => ['>'],
            'warning' => ['<'],
            'info' => ['='],
            default => []
        };

        return AnalyticsAlert::whereIn('condition', $conditions)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get alerts by metric
     */
    public function getByMetric(int $metricId): Collection
    {
        return AnalyticsAlert::where('analytics_metric_id', $metricId)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get recently triggered alerts
     */
    public function getRecentlyTriggered(int $hours = 24): Collection
    {
        return AnalyticsAlert::where('last_triggered_at', '>=', now()->subHours($hours))
            ->with('metric')
            ->orderBy('last_triggered_at', 'desc')
            ->get();
    }
}
