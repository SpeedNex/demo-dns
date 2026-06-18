<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    // 表名走 Laravel 默认（snake_case 复数） + config/database.php 的 `prefix`。
    // 历史上曾短暂写死 `dns_users` / `users`，现在统一交给 prefix 配置；
    // 想要改前缀只需调整 DB_TABLE_PREFIX，不用动模型。
    // protected $table = 'users';


    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'timezone',
        'locale',
        'current_team_id',
        'role',
        'status',
        // UI.md #50: plan_code moved to `subscriptions`; kept here as a
        // write-through cache populated by SubscriptionService.
        'plan_code',
        // UI.md #54: balance_minor is deprecated in favour of `wallets`.
        // Retained for backward compat with existing data and reports.
        'balance_minor',
        'balance_updated_at',
        'currency',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $user): void {
            if (empty($user->id)) {
                $user->id = 'usr_' . substr(hash('sha256', $user->email . microtime()), 0, 12);
            }
            if (empty($user->username)) {
                $user->username = self::buildUsernameFromEmail($user->email);
            }
        });
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'balance_updated_at' => 'datetime',
        ];
    }

    public function getNameAttribute(): ?string
    {
        return $this->attributes['username'] ?? null;
    }

    public function setNameAttribute(?string $value): void
    {
        $username = is_string($value) ? trim($value) : '';
        $this->attributes['username'] = $username !== '' ? $username : self::buildUsernameFromEmail((string) ($this->attributes['email'] ?? ''));
    }

    private static function buildUsernameFromEmail(?string $email): string
    {
        $localPart = strtolower((string) Str::before((string) $email, '@'));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $localPart) ?: 'user';

        return trim($normalized, '-._') !== '' ? trim($normalized, '-._') : 'user';
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }
}
