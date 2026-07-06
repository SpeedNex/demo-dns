// 回归测试 2026-07-06：验证修复 #12 singleflight 并发返回值
// 场景：50 个 goroutine 并发调用 DoOnce 拉取同一 profile
//   修复前：闭包变量 rawData/fetchVersion 只有真实执行 fn 的 goroutine 写值
//          等待的 goroutine 拿到 (nil, 0) → loadProfileIntoEngine(nil, 0) 错误配置
//   修复后：所有 goroutine 通过返回值共享 (data, version, err) → data 一定非 nil
package cache

import (
	"encoding/json"
	"sync"
	"sync/atomic"
	"testing"
	"time"
)

func TestDoOnce_Regression12_ConcurrentReturn(t *testing.T) {
	t.Run("concurrent 50 goroutines: all must receive non-nil data", func(t *testing.T) {
		pc := NewProfileCache("/tmp/test-regression-12", 100, 100, 30*time.Minute, 7*24*time.Hour)
		expectedData := json.RawMessage(`{"version":42,"profile_id":"p-concurrent","rules":["x.com"]}`)
		var fnCallCount int32 // 真实执行 fn 的次数（应只 1 次）

		fn := func() (json.RawMessage, int64, error) {
			atomic.AddInt32(&fnCallCount, 1)
			time.Sleep(50 * time.Millisecond) // 模拟 portal-web 拉取耗时
			return expectedData, 42, nil
		}

		const goroutines = 50
		var wg sync.WaitGroup
		wg.Add(goroutines)
		results := make([]json.RawMessage, goroutines)
		versions := make([]int64, goroutines)
		errs := make([]error, goroutines)

		for i := 0; i < goroutines; i++ {
			go func(idx int) {
				defer wg.Done()
				data, version, err := pc.DoOnce("p-concurrent", fn)
				results[idx] = data
				versions[idx] = version
				errs[idx] = err
			}(i)
		}
		wg.Wait()

		// 断言 1: fn 只执行 1 次
		if got := atomic.LoadInt32(&fnCallCount); got != 1 {
			t.Fatalf("[REGRESSION] fn should execute exactly 1 time, got %d", got)
		}

		// 断言 2: 全部 50 个 goroutine 都收到非 nil data
		nilCount := 0
		for i, d := range results {
			if d == nil {
				nilCount++
				t.Errorf("[CRITICAL REGRESSION] goroutine %d received nil data", i)
			}
		}
		if nilCount > 0 {
			t.Fatalf("[REGRESSION] %d goroutines got nil data — original bug has regressed!", nilCount)
		}

		// 断言 3: 全部 50 个 goroutine 都收到 version=42
		for i, v := range versions {
			if v != 42 {
				t.Errorf("goroutine %d got version %d, expected 42", i, v)
			}
		}

		// 断言 4: 全部 50 个 goroutine 都无 error
		for i, e := range errs {
			if e != nil {
				t.Errorf("goroutine %d got error: %v", i, e)
			}
		}

		t.Logf("[OK] 50 concurrent goroutines: 1 fn call, 50 non-nil data, all version=42")
	})

	t.Run("REGRESSION SCENARIO: same DoOnce + version check should not return nil for any goroutine", func(t *testing.T) {
		// 模拟修复后的 FetchProfile 流程：
		//   1) 内存未命中
		//   2) DoOnce 回源 → 拿 (data, version)
		//   3) loadProfileIntoEngine(profileID, data, version) — data 必须非 nil
		pc := NewProfileCache("/tmp/test-regression-12", 100, 100, 30*time.Minute, 7*24*time.Hour)
		loadEngineCalledWithNil := int32(0)
		loadEngineCalls := int32(0)

		loadProfileIntoEngine := func(data json.RawMessage, version int64) error {
			atomic.AddInt32(&loadEngineCalls, 1)
			if data == nil {
				atomic.AddInt32(&loadEngineCalledWithNil, 1)
				return nil
			}
			return nil
		}

		fn := func() (json.RawMessage, int64, error) {
			return json.RawMessage(`{"version":99,"profile_id":"p-load-test"}`), 99, nil
		}

		const goroutines = 30
		var wg sync.WaitGroup
		wg.Add(goroutines)
		for i := 0; i < goroutines; i++ {
			go func() {
				defer wg.Done()
				// 模拟 FetchProfile 修复后的逻辑
				data, version, err := pc.DoOnce("p-load-test", fn)
				if err != nil {
					t.Errorf("DoOnce error: %v", err)
					return
				}
				_ = loadProfileIntoEngine(data, version)
			}()
		}
		wg.Wait()

		if got := atomic.LoadInt32(&loadEngineCalledWithNil); got > 0 {
			t.Fatalf("[CRITICAL REGRESSION] %d loadProfileIntoEngine calls got nil data", got)
		}
		if got := atomic.LoadInt32(&loadEngineCalls); got != int32(goroutines) {
			t.Fatalf("expected %d engine load calls, got %d", goroutines, got)
		}
		t.Logf("[OK] %d concurrent goroutines, all loadProfileIntoEngine got non-nil data", goroutines)
	})
}
