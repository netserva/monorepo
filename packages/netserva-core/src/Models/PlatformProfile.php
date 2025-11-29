<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_type',     // 'provider', 'server', 'host', 'vhost'
        'profile_name',     // Filename without extension
        'title',            // Extracted title from markdown
        'description',      // Brief description
        'filepath',         // Original file path
        'content',          // Full markdown content
        'metadata',         // Parsed metadata from markdown
        'tags',             // Extracted tags
        'category',         // Category for organization
        'status',           // 'active', 'deprecated', 'archived'
        'migrated_at',      // When migrated to database
        'file_modified_at', // Last file modification
        'checksum',         // File content checksum
    ];

    protected $casts = [
        'metadata' => 'array',
        'tags' => 'array',
        'migrated_at' => 'datetime',
        'file_modified_at' => 'datetime',
    ];

    /**
     * Get profiles by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('profile_type', $type);
    }

    /**
     * Get active profiles
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get profiles by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Extract metadata from markdown content
     */
    public function extractMetadata(): array
    {
        $metadata = [];
        $lines = explode("\n", $this->content);

        foreach ($lines as $line) {
            // Look for markdown list items with key-value pairs
            if (preg_match('/^[-*]\s*\*\*([^*]+)\*\*:\s*(.+)$/', $line, $matches)) {
                $key = trim($matches[1]);
                $value = trim($matches[2]);
                $metadata[strtolower(str_replace(' ', '_', $key))] = $value;
            }
        }

        return $metadata;
    }

    /**
     * Extract title from markdown content
     */
    public function extractTitle(): ?string
    {
        $lines = explode("\n", $this->content);

        foreach ($lines as $line) {
            if (preg_match('/^#\s+(.+)$/', $line, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Extract tags from content
     */
    public function extractTags(): array
    {
        $tags = [];

        // Extract from title and headings
        if (preg_match_all('/^#+\s+(.+)$/m', $this->content, $matches)) {
            foreach ($matches[1] as $heading) {
                $words = explode(' ', strtolower($heading));
                foreach ($words as $word) {
                    $word = trim($word, '.,!?:');
                    if (strlen($word) > 3 && ! in_array($word, ['the', 'and', 'for', 'with', 'from'])) {
                        $tags[] = $word;
                    }
                }
            }
        }

        // Extract from metadata
        $metadata = $this->extractMetadata();
        if (isset($metadata['provider_type'])) {
            $tags[] = strtolower($metadata['provider_type']);
        }
        if (isset($metadata['purpose'])) {
            $tags[] = strtolower($metadata['purpose']);
        }

        return array_unique($tags);
    }

    /**
     * Get short description from content
     */
    public function extractDescription(): ?string
    {
        $lines = explode("\n", $this->content);
        $inOverview = false;

        foreach ($lines as $line) {
            // Look for overview section
            if (preg_match('/^#+\s+(Overview|Provider Overview|Host Overview)/i', $line)) {
                $inOverview = true;

                continue;
            }

            // Stop at next heading
            if ($inOverview && preg_match('/^#+\s+/', $line)) {
                break;
            }

            // Extract first meaningful paragraph in overview
            if ($inOverview && trim($line) && ! preg_match('/^[-*]/', $line)) {
                return trim($line);
            }
        }

        // Fallback: first paragraph after title
        $foundTitle = false;
        foreach ($lines as $line) {
            if (preg_match('/^#\s+/', $line)) {
                $foundTitle = true;

                continue;
            }

            if ($foundTitle && trim($line) && ! preg_match('/^#+/', $line)) {
                return trim($line);
            }
        }

        return null;
    }

    /**
     * Get summary information
     */
    public function getSummary(): array
    {
        return [
            'type' => $this->profile_type,
            'name' => $this->profile_name,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'status' => $this->status,
            'tags_count' => count($this->tags ?? []),
            'metadata_keys' => count($this->metadata ?? []),
            'content_length' => strlen($this->content),
            'migrated_at' => $this->migrated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Core\Database\Factories\PlatformProfileFactory::new();
    }
}
