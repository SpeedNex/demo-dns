<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NodeToken extends Model
{
    protected $table = 'node_tokens';
    protected $hidden = ['token_hash', 'hmac_key_hash', 'hmac_secret_encrypted'];

    protected static function booted(): void
    {
        static::creating(function (self $token): void {
            if (blank($token->token_prefix)) {
                $token->token_prefix = substr(Str::random(40), 0, 12) . '****';
            }
            if (blank($token->status)) {
                $token->status = 'active';
            }
        });
    }

    protected $fillable = [
        'node_id', 'token_prefix', 'token_hash',
        'hmac_key_hash', 'hmac_secret_encrypted', 'scopes',
        'status', 'last_used_at', 'expires_at', 'revoked_at', 'revoke_reason',
        'created_by_admin_id',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Create a new node token. The plaintext token and HMAC secret are
     * returned only once; only sha256 hashes / the encrypted secret are
     * persisted in the database.
     *
     * @return array{token: string, prefix: string, expires_at: \Carbon\Carbon|null, hmac_secret: string}
     */
    public static function createForNode(Node $node, ?int $ttlDays = 365, ?int $createdByAdminId = null): array
    {
        $plain = Str::random(40);
        $prefix = substr($plain, 0, 12) . '****';
        $hmacSecret = 'hmk_' . Str::lower(Str::random(32));

        $token = self::create([
            'node_id' => $node->id,
            'token_prefix' => $prefix,
            'token_hash' => hash('sha256', $plain),
            'hmac_key_hash' => hash('sha256', $hmacSecret),
            'hmac_secret_encrypted' => \Illuminate\Support\Facades\Crypt::encryptString($hmacSecret),
            'status' => 'active',
            'expires_at' => $ttlDays ? now()->addDays($ttlDays) : null,
            'created_by_admin_id' => $createdByAdminId,
        ]);

        return [
            'token' => $plain,
            'prefix' => $prefix,
            'expires_at' => $token->expires_at,
            'hmac_secret' => $hmacSecret,
        ];
    }

    public static function verifyPlainToken(string $plainToken): ?self
    {
        $hash = hash('sha256', $plainToken);
        return self::where('token_hash', $hash)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
