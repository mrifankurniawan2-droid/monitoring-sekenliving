<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSignature extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'app_name',
        'category',
        'domain_pattern',
        'badge_color',
        'icon',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check if given domain matches this signature pattern.
     */
    public function matchesDomain(string $domain): bool
    {
        $domain = strtolower(trim($domain, '. '));
        $patterns = preg_split('/[\r\n,]+/', $this->domain_pattern);

        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim($pattern, ". \t\n\r\0\x0B*"));
            if ($pattern === '') {
                continue;
            }

            // Strictly exact match or proper subdomain match (e.g. 'youtube.com' matches 'youtube.com' and 'sub.youtube.com')
            // Never use loose substring matching which causes 't.co' to falsely match '...microsoft.com'
            if ($domain === $pattern || str_ends_with($domain, '.'.$pattern)) {
                return true;
            }
        }

        return false;
    }
}
