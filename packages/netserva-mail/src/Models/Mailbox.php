<?php

namespace NetServa\Mail\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class Mailbox extends Model
{
    use HasFactory;

    protected $table = 'mailboxes';

    protected static function newFactory()
    {
        return \NetServa\Mail\Database\Factories\MailboxFactory::new();
    }

    protected $fillable = [
        // Basic identification
        'email',
        'full_name',
        'description',
        'mail_domain_id',

        // Authentication
        'password_hash',
        'is_active',

        // Storage and quota
        'quota_bytes',
        'used_bytes',

        // Service permissions
        'enable_imap',
        'enable_pop3',
        'enable_smtp',
        'enable_sieve',
        'enable_webmail',
        'enable_antispam',
        'enable_antivirus',

        // Forwarding
        'forward_to',

        // Auto-reply
        'auto_reply_enabled',
        'auto_reply_message',

        // Spam settings
        'spam_threshold_score',
        'spam_action',
        'spam_folder',
        'auto_learn_spam',

        // Sieve settings
        'sieve_active',
        'sieve_script',
        'sieve_updated_at',
        'sieve_rules',

        // Vacation settings
        'vacation_active',

        // Metadata
        'tags',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        // Boolean fields
        'is_active' => 'boolean',
        'enable_imap' => 'boolean',
        'enable_pop3' => 'boolean',
        'enable_smtp' => 'boolean',
        'enable_sieve' => 'boolean',
        'enable_webmail' => 'boolean',
        'enable_antispam' => 'boolean',
        'enable_antivirus' => 'boolean',
        'auto_reply_enabled' => 'boolean',
        'auto_learn_spam' => 'boolean',
        'sieve_active' => 'boolean',
        'vacation_active' => 'boolean',

        // Integer fields
        'quota_bytes' => 'integer',
        'used_bytes' => 'integer',
        'spam_threshold_score' => 'float',

        // Datetime fields
        'sieve_updated_at' => 'datetime',

        // JSON fields
        'tags' => 'json',
        'metadata' => 'json',
        'sieve_rules' => 'json',
    ];

    protected $hidden = [
        'password_hash',
    ];

    // Relationships
    public function mailDomain(): BelongsTo
    {
        return $this->belongsTo(MailDomain::class);
    }

    // Business Logic Methods

    /**
     * Set mailbox password with proper hashing
     */
    public function setPassword(string $password): void
    {
        $this->update([
            'password_hash' => Hash::make($password),
        ]);
    }

    /**
     * Verify password against stored hash
     */
    public function verifyPassword(string $password): bool
    {
        return Hash::check($password, $this->password_hash);
    }

    /**
     * Get quota usage percentage
     */
    public function getQuotaUsagePercentage(): float
    {
        if (! $this->quota_bytes) {
            return 0; // Unlimited quota
        }

        return min(100, ($this->used_bytes / $this->quota_bytes) * 100);
    }

    /**
     * Check if mailbox is over quota
     */
    public function isOverQuota(): bool
    {
        if (! $this->quota_bytes) {
            return false; // Unlimited quota
        }

        return $this->used_bytes >= $this->quota_bytes;
    }

    /**
     * Get formatted quota usage
     */
    public function getFormattedQuotaUsage(): string
    {
        $used = $this->formatBytes($this->used_bytes);

        if (! $this->quota_bytes) {
            return "{$used} / Unlimited";
        }

        $total = $this->formatBytes($this->quota_bytes);

        return "{$used} / {$total}";
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    // Query Scopes

    /**
     * Scope active mailboxes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by domain
     */
    public function scopeForDomain($query, $domainId)
    {
        return $query->where('mail_domain_id', $domainId);
    }

    /**
     * Scope over quota mailboxes
     */
    public function scopeOverQuota($query)
    {
        return $query->whereColumn('used_bytes', '>=', 'quota_bytes')
            ->whereNotNull('quota_bytes')
            ->where('quota_bytes', '>', 0);
    }
}
