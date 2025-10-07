<?php

namespace NetServa\Config\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NetServa\Ops\Traits\Auditable;

class DatabaseConnection extends Model
{
    use Auditable, HasFactory;

    protected static function newFactory()
    {
        return \Ns\Database\Database\Factories\DatabaseConnectionFactory::new();
    }

    protected $fillable = [
        'name',
        'host',
        'port',
        'engine',
        'username',
        'password',
        'ssl_enabled',
        'ssl_cert_path',
        'is_active',
    ];

    protected $casts = [
        'port' => 'integer',
        'ssl_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'ssl_enabled' => 0,
        'is_active' => 1,
    ];

    protected $hidden = [
        'password',
    ];

    // Relationships
    public function databases(): HasMany
    {
        return $this->hasMany(Database::class, 'connection_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByEngine($query, string $engine)
    {
        return $query->where('engine', $engine);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    // Methods
    public function testConnection(): bool
    {
        try {
            if ($this->engine === 'sqlite') {
                // For SQLite, test with memory database
                $pdo = new \PDO('sqlite::memory:');

                return true;
            }

            $config = [
                'driver' => $this->engine === 'postgresql' ? 'pgsql' : $this->engine,
                'host' => $this->host,
                'port' => $this->port,
                'username' => $this->username,
                'password' => decrypt($this->password),
                'database' => $this->engine === 'postgresql' ? 'postgres' : 'mysql',
            ];

            $pdo = new \PDO(
                sprintf('%s:host=%s;port=%d',
                    $config['driver'] === 'pgsql' ? 'pgsql' : 'mysql',
                    $config['host'],
                    $config['port']
                ),
                $config['username'],
                $config['password']
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getDatabaseCount(): int
    {
        return $this->databases()->count();
    }

    // Mutators
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = encrypt($value);
    }
}
