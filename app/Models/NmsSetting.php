<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class NmsSetting extends Model
{
    use HasFactory;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get a setting by key with a fallback default.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $setting = static::query()->find($key);

        if (! $setting || $setting->value === null || $setting->value === '') {
            return $default;
        }

        if ($key === 'mikrotik_pass') {
            try {
                return Crypt::decryptString($setting->value);
            } catch (\Throwable $e) {
                return $setting->value;
            }
        }

        return $setting->value;
    }

    /**
     * Set a setting value with encryption for sensitive keys.
     */
    public static function set(string $key, ?string $value): void
    {
        $storedValue = $value;

        if ($key === 'mikrotik_pass' && ! empty($value)) {
            $storedValue = Crypt::encryptString($value);
        }

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $storedValue]
        );
    }
}
