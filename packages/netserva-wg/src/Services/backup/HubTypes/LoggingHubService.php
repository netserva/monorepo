<?php

namespace NetServa\Wg\Services\HubTypes;

use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\SshConnectionService;
use NetServa\Wg\Models\WireguardHub;

class LoggingHubService
{
    public function __construct(
        private SshConnectionService $sshService
    ) {}

    /**
     * Configure hub as central logging aggregator
     */
    public function configureAsLoggingHub(WireguardHub $hub): bool
    {
        try {
            Log::info("Configuring logging hub: {$hub->name}");

            // Setup log aggregation infrastructure
            $this->setupLogAggregation($hub);

            // Configure log forwarding reception
            $this->configureLogReception($hub);

            // Setup log processing and analytics
            $this->setupLogProcessing($hub);

            // Configure log retention and rotation
            $this->configureLogRotation($hub);

            // Setup log forwarding to analytics manager
            $this->configureAnalyticsForwarding($hub);

            $hub->update([
                'configuration' => json_encode([
                    'logging_role' => true,
                    'log_aggregation' => true,
                    'log_processing' => true,
                    'analytics_forwarding' => true,
                    'configured_at' => now(),
                ]),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to configure logging hub {$hub->name}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Setup log aggregation infrastructure
     */
    private function setupLogAggregation(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $commands = [
            // Install logging tools
            'apt update && apt install -y rsyslog rsyslog-relp logrotate',
            'apt install -y jq gawk grep sed curl',

            // Create logging directories
            'mkdir -p /var/log/wireguard-central/{hubs,spokes,connections,security,performance}',
            'mkdir -p /var/log/wireguard-central/processed/{daily,weekly,monthly}',
            'mkdir -p /var/log/wireguard-central/alerts',
            'chmod 755 /var/log/wireguard-central',
            'chmod 755 /var/log/wireguard-central/*',

            // Create log processing scripts directory
            'mkdir -p /opt/wireguard-logging/{scripts,config,templates}',
            'chmod 755 /opt/wireguard-logging',
        ];

        foreach ($commands as $command) {
            $this->sshService->executeCommand($connection, $command);
        }
    }

    /**
     * Configure log reception from other hubs
     */
    private function configureLogReception(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        // Configure rsyslog to receive logs
        $rsyslogConfig = <<<'CONFIG'
# WireGuard Central Logging Configuration

# Enable reception of logs via TCP and UDP
$ModLoad imtcp
$InputTCPServerRun 51821

$ModLoad imudp
$UDPServerRun 51820

# Create separate log files for different sources
$template WireGuardLogFormat,"%TIMESTAMP% %HOSTNAME% %syslogtag%%msg:::sp-if-no-1st-sp%%msg:::drop-last-lf%\n"

# Hub logs
:programname, isequal, "wireguard-hub" /var/log/wireguard-central/hubs/hub-%HOSTNAME%.log;WireGuardLogFormat
& stop

# Spoke logs  
:programname, isequal, "wireguard-spoke" /var/log/wireguard-central/spokes/spoke-%HOSTNAME%.log;WireGuardLogFormat
& stop

# Connection logs
:programname, isequal, "wireguard-connection" /var/log/wireguard-central/connections/connection-%HOSTNAME%.log;WireGuardLogFormat
& stop

# Security events
:programname, isequal, "wireguard-security" /var/log/wireguard-central/security/security-%HOSTNAME%.log;WireGuardLogFormat
& stop

# Performance metrics
:programname, isequal, "wireguard-performance" /var/log/wireguard-central/performance/performance-%HOSTNAME%.log;WireGuardLogFormat
& stop

# All other WireGuard logs
:programname, startswith, "wireguard" /var/log/wireguard-central/general/wireguard-%HOSTNAME%.log;WireGuardLogFormat
& stop
CONFIG;

        $this->sshService->executeCommand(
            $connection,
            "cat > /etc/rsyslog.d/49-wireguard-central.conf << 'EOF'\n{$rsyslogConfig}\nEOF"
        );

        // Restart rsyslog to apply configuration
        $this->sshService->executeCommand($connection, 'systemctl restart rsyslog');
        $this->sshService->executeCommand($connection, 'systemctl enable rsyslog');
    }

    /**
     * Setup log processing and analytics
     */
    private function setupLogProcessing(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        // Create log processing script
        $processingScript = <<<'SCRIPT'
#!/bin/bash
# WireGuard Log Processing Script

LOG_DIR="/var/log/wireguard-central"
PROCESSED_DIR="/var/log/wireguard-central/processed"
ALERTS_DIR="/var/log/wireguard-central/alerts"
SCRIPT_LOG="/var/log/wireguard-processing.log"

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$SCRIPT_LOG"
}

# Process connection logs for statistics
process_connection_logs() {
    local date_suffix=$(date '+%Y-%m-%d')
    local output_file="$PROCESSED_DIR/daily/connections-$date_suffix.json"
    
    log_message "Processing connection logs for $date_suffix"
    
    # Find all connection logs from today
    find "$LOG_DIR/connections" -name "*.log" -newermt "$(date '+%Y-%m-%d 00:00:00')" | while read logfile; do
        hostname=$(basename "$logfile" .log | sed 's/connection-//')
        
        # Extract connection statistics
        total_connections=$(grep -c "connection established" "$logfile" 2>/dev/null || echo 0)
        failed_connections=$(grep -c "connection failed" "$logfile" 2>/dev/null || echo 0)
        active_duration=$(grep "connection duration" "$logfile" | awk '{sum+=$NF} END {print sum+0}')
        
        # Generate JSON output
        cat >> "$output_file" << EOF
{
  "date": "$date_suffix",
  "hostname": "$hostname",
  "total_connections": $total_connections,
  "failed_connections": $failed_connections,
  "total_duration_minutes": $active_duration,
  "processed_at": "$(date -Iseconds)"
}
EOF
    done
    
    log_message "Connection processing complete: $output_file"
}

# Process security logs for alerts
process_security_logs() {
    local date_suffix=$(date '+%Y-%m-%d')
    local alert_file="$ALERTS_DIR/security-alerts-$date_suffix.log"
    
    log_message "Processing security logs for alerts"
    
    # Check for security events
    find "$LOG_DIR/security" -name "*.log" -newermt "$(date '+%Y-%m-%d 00:00:00')" | while read logfile; do
        hostname=$(basename "$logfile" .log | sed 's/security-//')
        
        # Look for suspicious patterns
        if grep -q "authentication failure\|invalid key\|connection refused" "$logfile" 2>/dev/null; then
            echo "[$(date -Iseconds)] ALERT: Security event detected on $hostname" >> "$alert_file"
            grep "authentication failure\|invalid key\|connection refused" "$logfile" >> "$alert_file"
        fi
        
        # Check for unusual connection patterns
        connection_count=$(grep -c "connection attempt" "$logfile" 2>/dev/null || echo 0)
        if [ "$connection_count" -gt 100 ]; then
            echo "[$(date -Iseconds)] ALERT: High connection volume ($connection_count) on $hostname" >> "$alert_file"
        fi
    done
    
    if [ -f "$alert_file" ]; then
        log_message "Security alerts generated: $alert_file"
    fi
}

# Process performance metrics
process_performance_logs() {
    local date_suffix=$(date '+%Y-%m-%d')
    local metrics_file="$PROCESSED_DIR/daily/performance-$date_suffix.json"
    
    log_message "Processing performance metrics"
    
    find "$LOG_DIR/performance" -name "*.log" -newermt "$(date '+%Y-%m-%d 00:00:00')" | while read logfile; do
        hostname=$(basename "$logfile" .log | sed 's/performance-//')
        
        # Extract performance metrics
        avg_latency=$(grep "latency:" "$logfile" | awk '{sum+=$NF; count++} END {print (count>0) ? sum/count : 0}')
        max_bandwidth=$(grep "bandwidth:" "$logfile" | awk '{if($NF>max) max=$NF} END {print max+0}')
        packet_loss=$(grep "packet_loss:" "$logfile" | awk '{sum+=$NF; count++} END {print (count>0) ? sum/count : 0}')
        
        cat >> "$metrics_file" << EOF
{
  "date": "$date_suffix",
  "hostname": "$hostname",
  "avg_latency_ms": $avg_latency,
  "max_bandwidth_mbps": $max_bandwidth,
  "avg_packet_loss_percent": $packet_loss,
  "processed_at": "$(date -Iseconds)"
}
EOF
    done
    
    log_message "Performance processing complete: $metrics_file"
}

# Generate daily summary report
generate_daily_summary() {
    local date_suffix=$(date '+%Y-%m-%d')
    local summary_file="$PROCESSED_DIR/daily/summary-$date_suffix.json"
    
    log_message "Generating daily summary"
    
    # Count total log entries by type
    local hub_entries=$(find "$LOG_DIR/hubs" -name "*.log" -exec wc -l {} + | tail -1 | awk '{print $1}')
    local spoke_entries=$(find "$LOG_DIR/spokes" -name "*.log" -exec wc -l {} + | tail -1 | awk '{print $1}')
    local connection_entries=$(find "$LOG_DIR/connections" -name "*.log" -exec wc -l {} + | tail -1 | awk '{print $1}')
    local security_entries=$(find "$LOG_DIR/security" -name "*.log" -exec wc -l {} + | tail -1 | awk '{print $1}')
    
    cat > "$summary_file" << EOF
{
  "date": "$date_suffix",
  "total_hub_logs": $hub_entries,
  "total_spoke_logs": $spoke_entries,
  "total_connection_logs": $connection_entries,
  "total_security_logs": $security_entries,
  "processing_completed_at": "$(date -Iseconds)"
}
EOF
    
    log_message "Daily summary generated: $summary_file"
}

# Main processing routine
main() {
    log_message "=== Starting Log Processing ==="
    
    # Create processed directories
    mkdir -p "$PROCESSED_DIR"/{daily,weekly,monthly}
    mkdir -p "$ALERTS_DIR"
    
    # Process different log types
    process_connection_logs
    process_security_logs
    process_performance_logs
    generate_daily_summary
    
    log_message "=== Log Processing Complete ==="
}

main "$@"
SCRIPT;

        $this->sshService->executeCommand(
            $connection,
            "cat > /opt/wireguard-logging/scripts/process-logs.sh << 'EOF'\n{$processingScript}\nEOF"
        );

        $this->sshService->executeCommand(
            $connection,
            'chmod +x /opt/wireguard-logging/scripts/process-logs.sh'
        );

        // Setup cron job for log processing
        $this->sshService->executeCommand(
            $connection,
            'echo "0 * * * * root /opt/wireguard-logging/scripts/process-logs.sh" > /etc/cron.d/wireguard-log-processing'
        );
    }

    /**
     * Configure log rotation
     */
    private function configureLogRotation(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $logrotateConfig = <<<'CONFIG'
# WireGuard Central Logging Rotation
/var/log/wireguard-central/*/*.log {
    daily
    rotate 90
    compress
    delaycompress
    missingok
    notifempty
    create 644 syslog adm
    postrotate
        systemctl reload rsyslog
    endscript
}

/var/log/wireguard-central/processed/daily/*.json {
    weekly
    rotate 12
    compress
    delaycompress
    missingok
    notifempty
    create 644 root root
}

/var/log/wireguard-central/processed/weekly/*.json {
    monthly
    rotate 24
    compress
    delaycompress
    missingok
    notifempty
    create 644 root root
}

/var/log/wireguard-central/alerts/*.log {
    weekly
    rotate 52
    compress
    delaycompress
    missingok
    notifempty
    create 644 root root
}
CONFIG;

        $this->sshService->executeCommand(
            $connection,
            "cat > /etc/logrotate.d/wireguard-central << 'EOF'\n{$logrotateConfig}\nEOF"
        );
    }

    /**
     * Configure analytics forwarding
     */
    private function configureAnalyticsForwarding(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $forwardingScript = <<<'SCRIPT'
#!/bin/bash
# Forward processed logs to analytics manager

PROCESSED_DIR="/var/log/wireguard-central/processed"
ANALYTICS_ENDPOINT="${ANALYTICS_ENDPOINT:-http://analytics-manager:3001/api/wireguard/logs}"
SCRIPT_LOG="/var/log/wireguard-analytics-forwarding.log"

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$SCRIPT_LOG"
}

# Forward daily summaries to analytics
forward_daily_summaries() {
    local date_suffix=$(date '+%Y-%m-%d')
    
    # Find today's processed files
    find "$PROCESSED_DIR/daily" -name "*-$date_suffix.json" | while read processed_file; do
        filename=$(basename "$processed_file")
        log_type=$(echo "$filename" | cut -d'-' -f1)
        
        log_message "Forwarding $log_type data to analytics manager"
        
        if curl -s -X POST \
            -H "Content-Type: application/json" \
            -H "X-Source: wireguard-logging-hub" \
            --data "@$processed_file" \
            "$ANALYTICS_ENDPOINT/$log_type" > /dev/null; then
            log_message "Successfully forwarded $filename"
        else
            log_message "Failed to forward $filename"
        fi
    done
}

# Forward security alerts
forward_security_alerts() {
    local date_suffix=$(date '+%Y-%m-%d')
    local alert_file="/var/log/wireguard-central/alerts/security-alerts-$date_suffix.log"
    
    if [ -f "$alert_file" ]; then
        log_message "Forwarding security alerts to analytics manager"
        
        # Convert log file to JSON format
        local json_data=$(cat "$alert_file" | jq -R -s 'split("\n") | map(select(length > 0)) | {date: "'$date_suffix'", alerts: .}')
        
        if echo "$json_data" | curl -s -X POST \
            -H "Content-Type: application/json" \
            -H "X-Source: wireguard-logging-hub" \
            --data @- \
            "$ANALYTICS_ENDPOINT/security" > /dev/null; then
            log_message "Successfully forwarded security alerts"
        else
            log_message "Failed to forward security alerts"
        fi
    fi
}

# Main forwarding routine
main() {
    log_message "=== Starting Analytics Forwarding ==="
    
    forward_daily_summaries
    forward_security_alerts
    
    log_message "=== Analytics Forwarding Complete ==="
}

main "$@"
SCRIPT;

        $this->sshService->executeCommand(
            $connection,
            "cat > /opt/wireguard-logging/scripts/forward-analytics.sh << 'EOF'\n{$forwardingScript}\nEOF"
        );

        $this->sshService->executeCommand(
            $connection,
            'chmod +x /opt/wireguard-logging/scripts/forward-analytics.sh'
        );

        // Setup cron job for analytics forwarding
        $this->sshService->executeCommand(
            $connection,
            'echo "30 * * * * root /opt/wireguard-logging/scripts/forward-analytics.sh" > /etc/cron.d/wireguard-analytics-forwarding'
        );
    }

    /**
     * Setup log forwarding from other hubs to this logging hub
     */
    public function configureLogForwardingFromHub(WireguardHub $loggingHub, WireguardHub $sourceHub): bool
    {
        try {
            $connection = $this->sshService->getConnection($sourceHub->sshHost);

            // Configure rsyslog on source hub to forward to logging hub
            $forwardingConfig = <<<CONFIG
# Forward WireGuard logs to central logging hub
*.* @@{$loggingHub->endpoint}:51821

# Tag logs with appropriate program names
:programname, startswith, "wg" {
    \$template WireGuardFormat,"<%PRI%>%TIMESTAMP% %HOSTNAME% wireguard-%programname%: %msg%"
    @@{$loggingHub->endpoint}:51821;WireGuardFormat
    stop
}
CONFIG;

            $this->sshService->executeCommand(
                $connection,
                "cat >> /etc/rsyslog.d/50-wireguard-forwarding.conf << 'EOF'\n{$forwardingConfig}\nEOF"
            );

            $this->sshService->executeCommand($connection, 'systemctl restart rsyslog');

            Log::info("Configured log forwarding from {$sourceHub->name} to {$loggingHub->name}");

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to configure log forwarding from {$sourceHub->name}: ".$e->getMessage());

            return false;
        }
    }
}
