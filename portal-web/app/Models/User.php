<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    // V2.3: 主键名改为 uid
    protected $table = 'users';
    protected $primaryKey = 'uid';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'username',
        'email',
        'password',
        'plan_code',
        'locale',
        'status',
        'current_team_id',
        'last_login_at',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $user): void {
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
        ];
    }

    public function getNameAttribute(): ?string
    {
        return $this->attributes['username'] ?? null;
    }

    public function getIdAttribute(): ?int
    {
        return $this->getKey();
    }

    public function setNameAttribute(?string $value): void
    {
        $username = is_string($value) ? trim($value) : '';
        $this->attributes['username'] = $username !== '' ? $username : self::buildUsernameFromEmail((string) ($this->attributes['email'] ?? ''));
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'user_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'user_id');
    }

    public function wallet(): HasOne
    {
        // 钱包表已删除（SaaS 订阅模式），保留方法签名返回 null 避免调用方报错
        return $this->hasOne(Subscription::class, 'user_id')->whereRaw('1=0');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')->withPivot('role_key', 'joined_at');
    }

    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'user_id');
    }

    private static function buildUsernameFromEmail(?string $email): string
    {
        $localPart = strtolower((string) Str::before((string) $email, '@'));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $localPart) ?: 'user';

        return trim($normalized, '-._') !== '' ? trim($normalized, '-._') : 'user';
    }
}
