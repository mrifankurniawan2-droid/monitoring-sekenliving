<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class Router extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'ip_address',
        'port',
        'username',
        'password',
        'api_type',
        'use_ssl',
        'is_active',
        'last_sync_at',
        'last_sync_status',
        'device_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'use_ssl' => 'boolean',
            'is_active' => 'boolean',
            'last_sync_at' => 'datetime',
            'device_count' => 'integer',
        ];
    }

    /**
     * Encrypt password automatically on set.
     */
    public function setPasswordAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['password'] = null;
        } else {
            $this->attributes['password'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt password safely on get.
     */
    public function getDecryptedPassword(): ?string
    {
        if (empty($this->attributes['password'])) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['password']);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
