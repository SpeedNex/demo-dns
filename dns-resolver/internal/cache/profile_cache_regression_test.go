// 回归测试 2026-07-06：验证修复 #11 缓存版本反向判断
// 场景：
//   1) 缓存版本 == currentVersion → 应当命中（不被判过期）
//   2) 缓存版本 >  currentVersion → 应当命中（新版本可用）
//   3) 缓存版本 <  currentVersion → 应当不命中（异常态，回源）
package cache

import (
	"encoding/json"
	"testing"
	"time"
)

func TestGetFromMemoryWithVersionCheck_Regression11(t *testing.T) {
	t.Run("cache version equals current: should hit (not evict)", func(t *testing.T) {
		pc := NewProfileCache("/tmp/test-regression-11", 100, 100, 30*time.Minute, 7*24*time.Hour)
		data := json.RawMessage(`{"version":5,"profile_id":"p-test-1"}`)
		pc.SetToMemory("p-test-1", data, 5)

		// currentVersion == cache version (5)
		got, gotVer, ok := pc.GetFromMemoryWithVersionCheck("p-test-1", 5)
		if !ok {
			t.Fatalf("[REGRESSION] expected hit when version == current, got miss")
		}
		if gotVer != 5 {
			t.Fatalf("[REGRESSION] expected version 5, got %d", gotVer)
		}
		if string(got) != string(data) {
			t.Fatalf("[REGRESSION] data mismatch")
		}
		t.Logf("[OK] version==current: hit returned version=%d", gotVer)
	})

	t.Run("cache version greater than current: should hit", func(t *testing.T) {
		pc := NewProfileCache("/tmp/test-regression-11", 100, 100, 30*time.Minute, 7*24*time.Hour)
		data := json.RawMessage(`{"version":6,"profile_id":"p-test-2"}`)
		pc.SetToMemory("p-test-2", data, 6)

		// currentVersion(3) < cache version(6): should hit (new version available)
		_, gotVer, ok := pc.GetFromMemoryWithVersionCheck("p-test-2", 3)
		if !ok {
			t.Fatalf("[REGRESSION] expected hit when version > current, got miss")
		}
		if gotVer != 6 {
			t.Fatalf("[REGRESSION] expected version 6, got %d", gotVer)
		}
		t.Logf("[OK] version(6) > current(3): hit returned version=%d", gotVer)
	})

	t.Run("cache version less than current (abnormal): should miss + evict", func(t *testing.T) {
		pc := NewProfileCache("/tmp/test-regression-11", 100, 100, 30*time.Minute, 7*24*time.Hour)
		data := json.RawMessage(`{"version":2,"profile_id":"p-test-3"}`)
		pc.SetToMemory("p-test-3", data, 2)

		// currentVersion(8) > cache version(2): should miss + remove
		_, _, ok := pc.GetFromMemoryWithVersionCheck("p-test-3", 8)
		if ok {
			t.Fatalf("[REGRESSION] expected miss when version < current, got hit")
		}
		// 验证缓存被删除
		_, _, ok2 := pc.GetFromMemory("p-test-3")
		if ok2 {
			t.Fatalf("[REGRESSION] expected cache to be evicted, but still present")
		}
		t.Logf("[OK] version(2) < current(8): miss + cache evicted as expected")
	})

	t.Run("REGRESSION SCENARIO: cache version==current triggers NO portal fetch (original bug)", func(t *testing.T) {
		// 模拟 FetchProfile 流程：缓存版本 5 = 当前已加载版本 5
		// 修复前：版本检查失败 → 删缓存 → 强制回源
		// 修复后：版本检查通过 → 返回缓存 → 不回源
		pc := NewProfileCache("/tmp/test-regression-11", 100, 100, 30*time.Minute, 7*24*time.Hour)
		data := json.RawMessage(`{"version":5,"profile_id":"p-bug-1","rules":["a.com","b.com"]}`)
		pc.SetToMemory("p-bug-1", data, 5)

		// 假设本引擎已加载版本 5（currentVersion=5）
		portalFetchCount := 0
		simulateFetch := func(currentVersion int64) (json.RawMessage, int64, bool) {
			if _, version, ok := pc.GetFromMemoryWithVersionCheck("p-bug-1", currentVersion); ok {
				// 命中缓存，模拟直接 loadProfileIntoEngine
				return data, version, true
			}
			// 未命中，模拟回源 portal-web
			portalFetchCount++
			return nil, 0, false
		}

		// 连续调用 100 次（模拟 100 次 DNS 查询）
		for i := 0; i < 100; i++ {
			simulateFetch(5)
		}

		if portalFetchCount != 0 {
			t.Fatalf("[CRITICAL REGRESSION] expected 0 portal fetches when cache==local, got %d", portalFetchCount)
		}
		t.Logf("[OK] 100 DNS queries with cache version == current: 0 portal fetches (regression confirmed fixed)")
	})
}
