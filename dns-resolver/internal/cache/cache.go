// Package cache provides a thin Redis wrapper used by the dns-resolver for
// short-lived query deduplication. It is only initialised when the operator
// enables `redis.enabled: true` in configs/server.yaml, so the resolver must
// keep working in environments where Redis is intentionally absent.
package cache

import (
	"context"
	"errors"
	"fmt"
	"log"
	"sync/atomic"
	"time"

	"github.com/redis/go-redis/v9"

	"ocer-dns/dns-resolver/internal/config"
)

// Cache is a thin wrapper around go-redis that exposes only the operations
// the dns-resolver actually needs. We deliberately do not expose a generic
// Set/Get/GetDel to keep the surface minimal and avoid tempting callers into
// building flows that bypass the dedup contract.
type Cache struct {
	enabled atomic.Bool
	client  *redis.Client
	timeout time.Duration
}

// New initialises a Cache. If `redis.enabled` is false the returned Cache
// is a no-op (Enabled() == false, all other methods are inert) so the
// resolver can keep running without Redis.
func New(cfg config.RedisConfig) *Cache {
	cache := &Cache{timeout: 200 * time.Millisecond}
	if !cfg.Enabled {
		log.Printf("[缓存] Redis 已禁用 仅运行内存模式")
		return cache
	}
	if cfg.Addr == "" {
		log.Printf("[缓存] Redis 已启用但地址为空 仅运行内存模式")
		return cache
	}

	client := redis.NewClient(&redis.Options{
		Addr:         cfg.Addr,
		Password:     cfg.Password,
		DB:           cfg.DB,
		DialTimeout:  2 * time.Second,
		ReadTimeout:  500 * time.Millisecond,
		WriteTimeout: 500 * time.Millisecond,
		PoolSize:     16,
	})

	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer cancel()
	if err := client.Ping(ctx).Err(); err != nil {
		_ = client.Close()
		log.Printf("[缓存] Ping 失败 addr=%s err=%v 仅运行内存模式", cfg.Addr, err)
		return cache
	}

	cache.client = client
	cache.enabled.Store(true)
	log.Printf("[缓存] 已连接 addr=%s db=%d", cfg.Addr, cfg.DB)
	return cache
}

// Enabled reports whether the cache has a live Redis backend.
func (c *Cache) Enabled() bool {
	return c != nil && c.enabled.Load()
}

// Close releases the underlying Redis client. Safe to call on a disabled cache.
func (c *Cache) Close() error {
	if c == nil || c.client == nil {
		return nil
	}
	return c.client.Close()
}

// MarkSeen returns true if the key was newly recorded within the TTL window,
// false if it had already been seen. A disabled cache always returns true so
// callers never silently swallow events.
//
// `dedupKey` must be a stable per-event identifier; the canonical key is
// sha1(client_ip + "|" + qname + "|" + qtype) computed by the resolver.
func (c *Cache) MarkSeen(ctx context.Context, dedupKey string, ttl time.Duration) (firstSeen bool, err error) {
	if !c.Enabled() {
		return true, nil
	}
	if dedupKey == "" {
		return false, errors.New("redis: empty dedup key")
	}
	if ttl <= 0 {
		ttl = 5 * time.Second
	}

	cctx, cancel := context.WithTimeout(ctx, c.timeout)
	defer cancel()

	// SET key 1 NX EX <ttl> is atomic; the OK reply means the key was set,
	// which is exactly the "first seen" condition.
	ok, err := c.client.SetNX(cctx, "qdedup:"+dedupKey, 1, ttl).Result()
	if err != nil {
		// On a transport error we return firstSeen=true so the resolver keeps
		// counting and reporting. Surface the error so it can be logged.
		return true, fmt.Errorf("redis setnx: %w", err)
	}
	return ok, nil
}

// Incr bumps a counter at `counterKey` by 1, returning the new value. The
// counter is created with the supplied TTL on first increment. Disabled cache
// returns (0, nil) and the caller must avoid using the value when err is nil
// and Enabled() is false.
//
// The counter is intended for short-window QPS rate limiting, so the TTL
// should match the window length (e.g. 1s for per-second, 60s for per-minute).
func (c *Cache) Incr(ctx context.Context, counterKey string, ttl time.Duration) (int64, error) {
	if !c.Enabled() {
		return 0, nil
	}
	if counterKey == "" {
		return 0, errors.New("redis: empty counter key")
	}
	if ttl <= 0 {
		ttl = time.Minute
	}

	cctx, cancel := context.WithTimeout(ctx, c.timeout)
	defer cancel()

	pipe := c.client.Pipeline()
	incr := pipe.Incr(cctx, "qcount:"+counterKey)
	pipe.Expire(cctx, "qcount:"+counterKey, ttl)
	if _, err := pipe.Exec(cctx); err != nil {
		return 0, fmt.Errorf("redis incr pipeline: %w", err)
	}
	return incr.Val(), nil
}
