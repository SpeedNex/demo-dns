<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends BaseModel
{
    // V2.2: id 改为 BIGINT 自增
    public $incrementing = true;
    protected $keyType = 'int';

    /**
     * profile_id is a stable 6-char hex used as the DNS routing key.
     * It is generated when the profile is created and never changes.
     */
    public static function generateProfileUid(): string
    {
        do {
            $uid = substr(bin2hex(random_bytes(3)), 0, 6);
            if (! ctype_xdigit($uid)) {
                continue;
            }
            $exists = self::where('profile_id', $uid)->exists();
        } while ($exists);
        return $uid;
    }

    protected static function booted(): void
    {
        static::creating(function (self $profile): void {
            if (blank($profile->profile_id)) {
                $profile->profile_id = self::generateProfileUid();
            }
        });
    }

    protected $fillable = [
        'profile_id',
        'user_id',
        'name',
        'description',
        'default_action',
        'block_response',
        'is_default',
        'status',
        'security_enabled',
        'security_settings',
        'privacy_enabled',
        'privacy_settings',
        'parental_enabled',
        'parental_settings',
        'safesearch_enabled',
        'safe_search_enabled',
        'log_retention_days',
        'version',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'security_enabled' => 'boolean',
            'security_settings' => 'array',
            'privacy_enabled' => 'boolean',
            'privacy_settings' => 'array',
            'parental_enabled' => 'boolean',
            'parental_settings' => 'array',
            'safesearch_enabled' => 'boolean',
            'log_retention_days' => 'integer',
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function getSafeSearchEnabledAttribute(): bool
    {
        return (bool) ($this->attributes['safesearch_enabled'] ?? false);
    }

    public function setSafeSearchEnabledAttribute(bool $value): void
    {
        $this->attributes['safesearch_enabled'] = $value;
    }

    /**
     * Use profile_id (stable 6-char hex) for route model binding.
     * This is the key shown in URLs like /user/abc123/log.
     */
    public function getRouteKeyName(): string
    {
        return 'profile_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ProfileRule::class, 'profile_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'profile_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProfileVersion::class, 'target_profile_id')
            ->where('target_scope', 'profile');
    }
}
