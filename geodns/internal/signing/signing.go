// Package signing 提供 geodns 客户端的统一 HMAC-SHA256 签名工具。
//
// 与 portal-web VerifyRequestSignature 中间件对齐：
//   Required headers:
//     Authorization:    Bearer <token>
//     X-Hmac-Key:       <hmac_secret>
//     X-Signature:      hex(hmac_sha256(secret, ts\nMETHOD\npath\nsha256(body)))
//     X-Timestamp:      unix seconds (server tolerates ±300s)
//     X-Nonce:          random 16+ 字符（防重放，TTL=700s）
//
// 设计参考：dns-resolver/internal/agent/agent.go:596-610
// 2026-06-22 NEW P0#3 修复。
package signing

import (
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"io"
	"net/http"
	"strconv"
	"strings"
	"time"
)

// AddHMACHeaders 给 HTTP 请求添加完整 HMAC 签名头。
//  - token:       Bearer token
//  - hmacSecret:  HMAC 密钥（如果为空，回退使用 token 作为密钥，兼容老节点）
//  - body:        已读出的请求体（用于 body hash 计算；nil 表示 GET 无 body）
func AddHMACHeaders(req *http.Request, token, hmacSecret string, body []byte) {
	// 1) Bearer
	req.Header.Set("Authorization", "Bearer "+token)

	// 2) HMAC key (兼容老版本 V2 schema：secret 由客户端在 X-Hmac-Key 提供)
	secret := hmacSecret
	if secret == "" {
		secret = token
	}
	req.Header.Set("X-Hmac-Key", secret)

	// 3) 时间戳
	ts := strconv.FormatInt(time.Now().Unix(), 10)
	req.Header.Set("X-Timestamp", ts)

	// 4) Body SHA-256
	bodyHash := sha256.Sum256(body)
	bodyHashHex := hex.EncodeToString(bodyHash[:])

	// 5) Canonical: ts\nMETHOD\npath\nbodyHash
	canonical := ts + "\n" + strings.ToUpper(req.Method) + "\n" + req.URL.Path + "\n" + bodyHashHex

	// 6) HMAC-SHA256 签名
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write([]byte(canonical))
	req.Header.Set("X-Signature", hex.EncodeToString(mac.Sum(nil)))

	// 7) Nonce
	nonce := make([]byte, 16)
	if _, err := io.ReadFull(rand.Reader, nonce); err == nil {
		req.Header.Set("X-Nonce", hex.EncodeToString(nonce))
	}
}
