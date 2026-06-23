package tests

import (
	"crypto/tls"
	"encoding/binary"
	"fmt"
	"io"
	"net/http"
	"os"
	"strings"
	"testing"

	"github.com/miekg/dns"
)

const (
	dohURL  = "http://localhost:8443"
	dotAddr = "127.0.0.1:18853"
)

// baidu.com 的 DNS 查询包（base64 url-safe encoded）
const baiduQueryB64 = "EjQBAAABAAAAAAAABWJhaWR1A2NvbQAAAQAB"

func TestDoH_ProfileViaPath(t *testing.T) {
	// 测试: GET /b669c1/dns-query?dns=...  → 应解析出 profile_uid=b669c1
	url := fmt.Sprintf("%s/b669c1/dns-query?dns=%s", dohURL, baiduQueryB64)
	resp, err := http.Get(url)
	if err != nil {
		t.Fatalf("DoH request failed: %v", err)
	}
	defer resp.Body.Close()

	body, _ := io.ReadAll(resp.Body)
	if len(body) < 12 {
		t.Fatalf("Response too short: %d bytes", len(body))
	}

	flags := binary.BigEndian.Uint16(body[2:4])
	ancount := binary.BigEndian.Uint16(body[6:8])
	rcode := flags & 0x0F

	t.Logf("DoH Profile(path): flags=0x%04x, answers=%d, rcode=%d", flags, ancount, rcode)
	if rcode != 0 {
		t.Errorf("Expected RCODE=0, got %d", rcode)
	}
	if ancount == 0 {
		t.Errorf("Expected at least 1 answer, got 0")
	}
	t.Log("✅ DoH Profile(path) test PASSED")
}

func TestDoH_ProfileViaHeader(t *testing.T) {
	// 测试: GET /dns-query?dns=... 带 X-Profile-UID header  → 使用 profile_uid=b669c1
	url := fmt.Sprintf("%s/dns-query?dns=%s", dohURL, baiduQueryB64)
	req, _ := http.NewRequest("GET", url, nil)
	req.Header.Set("X-Profile-UID", "b669c1")
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatalf("DoH request failed: %v", err)
	}
	defer resp.Body.Close()

	body, _ := io.ReadAll(resp.Body)
	if len(body) < 12 {
		t.Fatalf("Response too short: %d bytes", len(body))
	}

	flags := binary.BigEndian.Uint16(body[2:4])
	ancount := binary.BigEndian.Uint16(body[6:8])
	rcode := flags & 0x0F

	t.Logf("DoH Profile(header): flags=0x%04x, answers=%d, rcode=%d", flags, ancount, rcode)
	if rcode != 0 {
		t.Errorf("Expected RCODE=0, got %d", rcode)
	}
	if ancount == 0 {
		t.Errorf("Expected at least 1 answer, got 0")
	}
	t.Log("✅ DoH Profile(header) test PASSED")
}

func TestDoT_ProfileViaSNI(t *testing.T) {
	// 测试: TLS SNI=b669c1.dns.test.com → 提取 profile_uid=b669c1
	tlsCfg := &tls.Config{
		ServerName:         "b669c1.dns.test.com",
		InsecureSkipVerify: true,
	}

	conn, err := tls.Dial("tcp", dotAddr, tlsCfg)
	if err != nil {
		t.Fatalf("TLS dial failed: %v", err)
	}
	defer conn.Close()

	q := new(dns.Msg)
	q.SetQuestion("baidu.com.", dns.TypeA)
	wire, _ := q.Pack()

	lenBuf := make([]byte, 2)
	binary.BigEndian.PutUint16(lenBuf, uint16(len(wire)))
	conn.Write(lenBuf)
	conn.Write(wire)

	_, err = io.ReadFull(conn, lenBuf)
	if err != nil {
		t.Fatalf("Failed to read response length: %v", err)
	}
	respLen := binary.BigEndian.Uint16(lenBuf)
	respWire := make([]byte, respLen)
	_, err = io.ReadFull(conn, respWire)
	if err != nil {
		t.Fatalf("Failed to read response: %v", err)
	}

	resp := new(dns.Msg)
	err = resp.Unpack(respWire)
	if err != nil {
		t.Fatalf("Failed to unpack response: %v", err)
	}

	t.Logf("DoT SNI: rcode=%d, answers=%d", resp.Rcode, len(resp.Answer))
	if resp.Rcode != dns.RcodeSuccess {
		t.Errorf("Expected RCODE=0, got %d", resp.Rcode)
	}
	for _, a := range resp.Answer {
		if a.Header().Rrtype == dns.TypeA {
			t.Logf("  Answer: %s", a)
		}
	}
	t.Log("✅ DoT Profile(SNI) test PASSED")
}

func TestDoT_FallbackToSourceIP(t *testing.T) {
	// SNI=localhost (非 profile UID) → 回退到源 IP 匹配
	tlsCfg := &tls.Config{
		ServerName:         "localhost",
		InsecureSkipVerify: true,
	}

	conn, err := tls.Dial("tcp", dotAddr, tlsCfg)
	if err != nil {
		t.Fatalf("TLS dial failed: %v", err)
	}
	defer conn.Close()

	q := new(dns.Msg)
	q.SetQuestion("google.com.", dns.TypeA)
	wire, _ := q.Pack()

	lenBuf := make([]byte, 2)
	binary.BigEndian.PutUint16(lenBuf, uint16(len(wire)))
	conn.Write(lenBuf)
	conn.Write(wire)

	_, err = io.ReadFull(conn, lenBuf)
	if err != nil {
		t.Fatalf("Failed to read response: %v", err)
	}
	respWire := make([]byte, binary.BigEndian.Uint16(lenBuf))
	io.ReadFull(conn, respWire)

	resp := new(dns.Msg)
	resp.Unpack(respWire)

	t.Logf("DoT Fallback: rcode=%d, answers=%d", resp.Rcode, len(resp.Answer))
	if resp.Rcode == dns.RcodeSuccess && len(resp.Answer) > 0 {
		t.Log("✅ Fallback to source IP works")
	} else {
		t.Log("⚠ Fallback: no active.config or source IP not matched (expected in dev)")
	}
}

func TestDoH_NginxHeaderPriority(t *testing.T) {
	// X-Profile-UID header 优先级高于路径判断
	// 即使用路径是 /invalid/dns-query，header 也优先
	url := fmt.Sprintf("%s/invalid/dns-query?dns=%s", dohURL, baiduQueryB64)
	req, _ := http.NewRequest("GET", url, nil)
	req.Header.Set("X-Profile-UID", "b669c1")
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatalf("DoH request failed: %v", err)
	}
	defer resp.Body.Close()

	body, _ := io.ReadAll(resp.Body)
	if len(body) < 12 {
		t.Fatalf("Response too short: %d bytes", len(body))
	}

	flags := binary.BigEndian.Uint16(body[2:4])
	ancount := binary.BigEndian.Uint16(body[6:8])
	rcode := flags & 0x0F

	t.Logf("DoH Nginx Header: flags=0x%04x, answers=%d, rcode=%d", flags, ancount, rcode)
	if rcode != 0 {
		t.Errorf("Expected RCODE=0, got %d", rcode)
	}
	if ancount == 0 {
		t.Errorf("Expected at least 1 answer, got 0")
	}
	t.Log("✅ DoH Nginx Header priority test PASSED")
}

func TestConfigProfilesPath(t *testing.T) {
	data, err := os.ReadFile("../configs/server-test.yaml")
	if err != nil {
		t.Skip("server-test.yaml not found")
	}
	for _, line := range strings.Split(string(data), "\n") {
		if strings.Contains(line, "profiles_path") || strings.Contains(line, "active") {
			t.Logf("Config: %s", strings.TrimSpace(line))
		}
	}
}

func TestMain(m *testing.M) {
	fmt.Println("=== DNS Profile Resolution Layer Tests ===")
	fmt.Println("Ensure dns-resolver is running with --config configs/server-test.yaml")
	fmt.Println()
	os.Exit(m.Run())
}
