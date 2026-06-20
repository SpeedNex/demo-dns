<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * Backend administrator account. Login and session are isolated from users.
 * User-facing registration and login APIs must never touch this model.
 */
class Admin extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    // V2.3: 主键名改为 admin_id
    protected $table = 'admins';
    protected $primaryKey = 'admin_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'username',
        'email',
        'password',
        'status',
        'is_super',
        'locale',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $admin): void {
            if (empty($admin->username)) {
                $admin->username = self::buildUsernameFromEmail($admin->email);
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
            'is_super' => 'boolean',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Return the column name for the "password" field.
     * Implements Laravel's Authenticatable contract.
     */
    public function getAuthPassword(): string
    {
        return $this->password;
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

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminRole::class,
            'admin_user_roles',
            'admin_id',
            'admin_role_id'
        );
    }

    public function assignRole(string $roleCode, ?int $assignedBy = null): void
    {
        $role = AdminRole::query()->where('code', $roleCode)->first();
        if ($role === null) {
            throw new \RuntimeException('Admin role not found: ' . $roleCode);
        }
        $exists = DB::table('admin_user_roles')
            ->where('admin_id', $this->admin_id)
            ->where('admin_role_id', $role->id)
            ->exists();
        if ($exists) {
            return;
        }
        DB::table('admin_user_roles')->insert([
            'admin_id'   => $this->admin_id,
            'admin_role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function hasNavKey(string $navKey): bool
    {
        if ($this->is_super === true) {
            return true;
        }
        return DB::table('admin_role_nav_rules as r')
            ->join('admin_user_roles as ur', 'ur.admin_role_id', '=', 'r.admin_role_id')
            ->where('ur.admin_id', $this->admin_id)
            ->where('r.nav_key', $navKey)
            ->where('r.visible', true)
            ->exists();
    }

    private static function buildUsernameFromEmail(?string $email): string
    {
        $localPart = strtolower((string) Str::before((string) $email, '@'));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $localPart) ?: 'admin';

        return trim($normalized, '-._') !== '' ? trim($normalized, '-._') : 'admin';
    }
}
