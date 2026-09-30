<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'router_id',
        'router_name',
        'ip_address',
        'mac_address',
        'hostname',
        'comment',
        'interface',
        'status',
        'source',
        'is_online',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'router_id' => 'integer',
            'is_online' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Router, $this>
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Get display name for device.
     */
    public function getDisplayNameAttribute(): string
    {
        if (! empty($this->hostname)) {
            return $this->hostname;
        }

        if (! empty($this->comment)) {
            return $this->comment;
        }

        return $this->ip_address;
    }
}
