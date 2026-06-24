// Package cache implements the Profile 二级缓存（Memory + Disk）系统，
// 用于 Resolver 的按需配置拉取架构。
//
// 架构：
//   Memory Cache: max 5000 entries, LRU, 30min TTL
//   Disk Cache:   max 20000 entries, LRU, 7天 TTL
//   SingleFlight: 同一 profile 并发请求仅回源 1 次
package cache

import (
	"encoding/json"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"time"

	"golang.org/x/sync/singleflight"
)

// CacheEnvelope 是磁盘缓存文件的包装结构。
type CacheEnvelope struct {
	ProfileID string          `json:"profile_id"`
	Version   int64           `json:"version"`
	CachedAt  string          `json:"cached_at"`
	Data      json.RawMessage `json:"data"`
}

// ProfileCache 是 Profile 配置的二级缓存。
// 线程安全。
type ProfileCache struct {
	mu       sync.RWMutex
	memory   map[string]*cacheEntry   // profileID → entry
	mruList  []string                 // LRU 顺序（最近使用在末尾）
	diskRoot string                   // 磁盘缓存根目录

	maxMemory int // 内存上限
	maxDisk   int // 磁盘上限
	memTTL    time.Duration
	diskTTL   time.Duration

	singleGroup singleflight.Group // 防击穿
}

type cacheEntry struct {
	Data         json.RawMessage `json:"data"`
	Version      int64           `json:"version"`
	LastUsedAt   time.Time       `json:"last_used_at"`
	CachedAt     time.Time       `json:"cached_at"`
	DiskPath     string          `json:"-"` // 磁盘路径
}

// NewProfileCache 创建新的 Profile 缓存。
func NewProfileCache(diskRoot string, maxMemory, maxDisk int, memTTL, diskTTL time.Duration) *ProfileCache {
	return &ProfileCache{
		memory:    make(map[string]*cacheEntry, maxMemory),
		diskRoot:  diskRoot,
		maxMemory: maxMemory,
		maxDisk:   maxDisk,
		memTTL:    memTTL,
		diskTTL:   diskTTL,
	}
}

// ==================== Memory Cache ====================

// GetFromMemory 从内存缓存读取 Profile。
// 返回 (data, version, ok)。
func (pc *ProfileCache) GetFromMemory(profileID string) (json.RawMessage, int64, bool) {
	pc.mu.RLock()
	entry, ok := pc.memory[profileID]
	pc.mu.RUnlock()
	if !ok {
		return nil, 0, false
	}
	// 检查是否过期
	if time.Since(entry.LastUsedAt) > pc.memTTL {
		pc.RemoveFromMemory(profileID)
		return nil, 0, false
	}
	// 更新最后使用时间
	pc.touch(profileID)
	return entry.Data, entry.Version, true
}

// SetToMemory 写入 Profile 到内存缓存。
func (pc *ProfileCache) SetToMemory(profileID string, data json.RawMessage, version int64) {
	pc.mu.Lock()
	defer pc.mu.Unlock()

	// 检查是否已达上限 → LRU 淘汰
	if len(pc.memory) >= pc.maxMemory {
		pc.evictMemoryLRU()
	}

	pc.memory[profileID] = &cacheEntry{
		Data:       data,
		Version:    version,
		LastUsedAt: time.Now(),
		CachedAt:   time.Now(),
	}
	pc.mruList = append(pc.mruList, profileID)
}

// RemoveFromMemory 从内存缓存删除 Profile。
func (pc *ProfileCache) RemoveFromMemory(profileID string) {
	pc.mu.Lock()
	defer pc.mu.Unlock()
	delete(pc.memory, profileID)
	// 从 LRU 列表移除
	for i, id := range pc.mruList {
		if id == profileID {
			pc.mruList = append(pc.mruList[:i], pc.mruList[i+1:]...)
			break
		}
	}
}

// touch 更新 Profile 的最后使用时间（移到 LRU 末尾）。
func (pc *ProfileCache) touch(profileID string) {
	pc.mu.Lock()
	defer pc.mu.Unlock()
	if entry, ok := pc.memory[profileID]; ok {
		entry.LastUsedAt = time.Now()
	}
	// 移到 LRU 列表末尾
	for i, id := range pc.mruList {
		if id == profileID {
			pc.mruList = append(pc.mruList[:i], pc.mruList[i+1:]...)
			pc.mruList = append(pc.mruList, profileID)
			break
		}
	}
}

// evictMemoryLRU 淘汰最久未使用的内存条目。
func (pc *ProfileCache) evictMemoryLRU() {
	if len(pc.mruList) == 0 {
		return
	}
	// 最久未使用在头部
	evict := pc.mruList[0]
	pc.mruList = pc.mruList[1:]
	delete(pc.memory, evict)
	log.Printf("ProfileCache: evicted from memory (LRU): %s", evict)
}

// ==================== Disk Cache ====================

// profileDiskPath 返回 Profile 的磁盘缓存路径（两级目录）。
func (pc *ProfileCache) profileDiskPath(profileID string) string {
	if len(profileID) < 2 {
		return filepath.Join(pc.diskRoot, profileID+".json")
	}
	return filepath.Join(pc.diskRoot, profileID[:2], profileID+".json")
}

// GetFromDisk 从磁盘缓存读取 Profile。
// 返回 (data, version, ok)。
func (pc *ProfileCache) GetFromDisk(profileID string) (json.RawMessage, int64, bool) {
	path := pc.profileDiskPath(profileID)
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, 0, false
	}

	var envelope CacheEnvelope
	if err := json.Unmarshal(data, &envelope); err != nil {
		return nil, 0, false
	}

	// 检查磁盘 TTL
	cachedAt, _ := time.Parse(time.RFC3339, envelope.CachedAt)
	if time.Since(cachedAt) > pc.diskTTL {
		os.Remove(path)
		log.Printf("ProfileCache: evicted from disk (TTL): %s", profileID)
		return nil, 0, false
	}

	return envelope.Data, envelope.Version, true
}

// SetToDisk 写入 Profile 到磁盘缓存。
func (pc *ProfileCache) SetToDisk(profileID string, data json.RawMessage, version int64) error {
	dir := filepath.Dir(pc.profileDiskPath(profileID))
	if err := os.MkdirAll(dir, 0755); err != nil {
		return fmt.Errorf("create cache dir: %w", err)
	}

	// 检查磁盘上限
	pc.evictDiskIfNeeded()

	envelope := CacheEnvelope{
		ProfileID: profileID,
		Version:   version,
		CachedAt:  time.Now().UTC().Format(time.RFC3339),
		Data:      data,
	}

	payload, err := json.Marshal(envelope)
	if err != nil {
		return fmt.Errorf("marshal cache envelope: %w", err)
	}

	// 原子写盘
	tmpPath := pc.profileDiskPath(profileID) + ".tmp"
	if err := os.WriteFile(tmpPath, payload, 0644); err != nil {
		return fmt.Errorf("write temp file: %w", err)
	}
	if err := os.Rename(tmpPath, pc.profileDiskPath(profileID)); err != nil {
		return fmt.Errorf("rename temp file: %w", err)
	}

	return nil
}

// RemoveFromDisk 从磁盘缓存删除 Profile。
func (pc *ProfileCache) RemoveFromDisk(profileID string) {
	os.Remove(pc.profileDiskPath(profileID))
}

// evictDiskIfNeeded 检查磁盘文件数量，超过上限执行 LRU 淘汰。
func (pc *ProfileCache) evictDiskIfNeeded() {
	files := pc.listDiskFiles()
	if len(files) < pc.maxDisk {
		return
	}

	// 按修改时间排序，删除最旧的
	sort.Slice(files, func(i, j int) bool {
		infoI, _ := os.Stat(files[i])
		infoJ, _ := os.Stat(files[j])
		return infoI.ModTime().Before(infoJ.ModTime())
	})

	// 删除最旧的 10%
	deleteCount := len(files) - pc.maxDisk + pc.maxDisk/10
	for i := 0; i < deleteCount && i < len(files); i++ {
		os.Remove(files[i])
		name := filepath.Base(files[i])
		log.Printf("ProfileCache: evicted from disk (LRU): %s", strings.TrimSuffix(name, ".json"))
	}
}

// listDiskFiles 列出磁盘缓存目录下所有 .json 文件。
func (pc *ProfileCache) listDiskFiles() []string {
	var files []string
	filepath.Walk(pc.diskRoot, func(path string, info os.FileInfo, err error) error {
		if err != nil || info.IsDir() {
			return nil
		}
		if strings.HasSuffix(path, ".json") && !strings.HasSuffix(path, ".tmp.json") {
			files = append(files, path)
		}
		return nil
	})
	return files
}

// ==================== SingleFlight ====================

// DoOnce 包装 singleflight.Group.Do，确保同一 Profile 仅回源一次。
// fn 是回源拉取函数，返回 data 和 error。
func (pc *ProfileCache) DoOnce(profileID string, fn func() (json.RawMessage, int64, error)) (json.RawMessage, int64, error) {
	result, err, _ := pc.singleGroup.Do(profileID, func() (interface{}, error) {
		data, version, err := fn()
		if err != nil {
			return nil, err
		}
		return &cacheResult{data: data, version: version}, nil
	})
	if err != nil {
		return nil, 0, err
	}
	r := result.(*cacheResult)
	return r.data, r.version, nil
}

type cacheResult struct {
	data    json.RawMessage
	version int64
}

// ==================== 启动时恢复 ====================

// LoadFromDiskOnStartup 启动时扫描磁盘缓存，加载有效的 Profile 到内存。
// 只加载未过期的，过期文件自动清理。
func (pc *ProfileCache) LoadFromDiskOnStartup() {
	files := pc.listDiskFiles()
	loaded := 0
	for _, path := range files {
		data, err := os.ReadFile(path)
		if err != nil {
			continue
		}
		var envelope CacheEnvelope
		if err := json.Unmarshal(data, &envelope); err != nil {
			continue
		}
		if envelope.ProfileID == "" || len(envelope.Data) == 0 {
			continue
		}

		cachedAt, _ := time.Parse(time.RFC3339, envelope.CachedAt)
		if time.Since(cachedAt) > pc.diskTTL {
			os.Remove(path)
			continue
		}

		// 不立即加载到内存（按需加载），只记录磁盘路径
		pc.mu.Lock()
		if _, exists := pc.memory[envelope.ProfileID]; !exists {
			// 记录到内存（marked for disk load）
			pc.memory[envelope.ProfileID] = &cacheEntry{
				Data:       envelope.Data,
				Version:    envelope.Version,
				LastUsedAt: time.Now(), // 启动时间作为 lastUsed
				CachedAt:   cachedAt,
				DiskPath:   path,
			}
			pc.mruList = append(pc.mruList, envelope.ProfileID)
		}
		pc.mu.Unlock()
		loaded++
	}
	log.Printf("ProfileCache: loaded %d profiles from disk on startup", loaded)
}
