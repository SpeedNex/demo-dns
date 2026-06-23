package dnsserver

import (
	"crypto/ecdsa"
	"crypto/elliptic"
	"crypto/rand"
	"crypto/tls"
	"crypto/x509"
	"crypto/x509/pkix"
	"encoding/pem"
	"log"
	"math/big"
	"net"
	"os"
	"sync/atomic"
	"time"
)

// LoadTLSConfig 加载 TLS 配置。
// 如果 certFile/keyFile 非空，使用 GetCertificate 回调动态加载（支持热更新，无需重启）；
// 内部维护原子缓存：
//   - 首次启动无证书时 → 自签名兜底（通常 install 尚未完成）
//   - 一旦成功加载过正式证书 → 后续加载失败使用缓存证书，不回退自签名
// 如果 certFile/keyFile 为空，直接生成自签名证书（开发测试用）。
func LoadTLSConfig(certFile, keyFile string) (*tls.Config, error) {
	if certFile != "" && keyFile != "" {
		log.Printf("tls: will load certificate from %s (hot-reload via GetCertificate)", certFile)

		var (
			cachedCert  atomic.Value // stores *tls.Certificate
			hasRealCert atomic.Bool
		)

		// 首次尝试加载，预先填充缓存（可能失败，不影响后续握手）
		if initialCert, err := tls.LoadX509KeyPair(certFile, keyFile); err == nil {
			cachedCert.Store(&initialCert)
			hasRealCert.Store(true)
			log.Printf("tls: preloaded certificate from %s", certFile)
		}

		return &tls.Config{
			GetCertificate: func(hello *tls.ClientHelloInfo) (*tls.Certificate, error) {
				cert, err := tls.LoadX509KeyPair(certFile, keyFile)
				if err != nil {
					// 有缓存 → 用最后一次成功的证书，不中断服务
					if cached := cachedCert.Load(); cached != nil {
						log.Printf("tls: reload failed (%v) — using cached cert", err)
						return cached.(*tls.Certificate), nil
					}
					// 从未成功加载过 → 自签名兜底（仅首次启动）
					log.Printf("tls: no cached cert available, falling back to self-signed: %v", err)
					return generateSelfSignedCert()
				}
				// 成功加载 → 更新缓存
				cachedCert.Store(&cert)
				if !hasRealCert.Load() {
					hasRealCert.Store(true)
					log.Printf("tls: first successful load of %s", certFile)
				}
				return &cert, nil
			},
			MinVersion: tls.VersionTLS12,
		}, nil
	}

	log.Printf("tls: no certificate configured, generating self-signed (dev-only)")
	c, err := generateSelfSignedCert()
	if err != nil {
		return nil, err
	}
	return &tls.Config{
		Certificates: []tls.Certificate{*c},
		MinVersion:   tls.VersionTLS12,
	}, nil
}

// generateSelfSignedCert 生成自签名 ECDSA 证书，有效期 365 天。
func generateSelfSignedCert() (*tls.Certificate, error) {
	key, err := ecdsa.GenerateKey(elliptic.P256(), rand.Reader)
	if err != nil {
		return nil, err
	}

	serial, err := rand.Int(rand.Reader, new(big.Int).Lsh(big.NewInt(1), 128))
	if err != nil {
		return nil, err
	}

	tmpl := &x509.Certificate{
		SerialNumber: serial,
		Subject: pkix.Name{
			CommonName:   "ocer-dns-resolver",
			Organization: []string{"OcerDNS Dev"},
		},
		NotBefore:             time.Now(),
		NotAfter:              time.Now().Add(365 * 24 * time.Hour),
		KeyUsage:              x509.KeyUsageDigitalSignature | x509.KeyUsageKeyEncipherment,
		ExtKeyUsage:           []x509.ExtKeyUsage{x509.ExtKeyUsageServerAuth},
		BasicConstraintsValid: true,
		IPAddresses:           []net.IP{net.ParseIP("127.0.0.1"), net.IPv6loopback},
		DNSNames:              []string{"localhost", "ocer-dns-resolver", "dns.test.com", "*.dns.test.com"},
	}

	certDER, err := x509.CreateCertificate(rand.Reader, tmpl, tmpl, &key.PublicKey, key)
	if err != nil {
		return nil, err
	}

	// 将临时证书写入文件，方便调试（同时写 DER 和 PEM 两种格式）
	certPath := "/tmp/ocer-dns-dev.crt"
	_ = os.WriteFile(certPath, certDER, 0644)

	pemPath := "/tmp/ocer-dns-dev.pem"
	pemBlock := &pem.Block{Type: "CERTIFICATE", Bytes: certDER}
	_ = os.WriteFile(pemPath, pem.EncodeToMemory(pemBlock), 0644)

	keyBytes, err := x509.MarshalECPrivateKey(key)
	if err != nil {
		return nil, err
	}
	_ = os.WriteFile("/tmp/ocer-dns-dev.key", keyBytes, 0600)

	log.Printf("tls: self-signed cert written to %s / %s (dev-only, do not use in production)", pemPath, certPath)

	// 将证书写入 PEM 格式，方便 kdig +tls-ca 使用
	_ = os.WriteFile("/tmp/ocer-dns-ca.pem", pem.EncodeToMemory(pemBlock), 0644)

	cert := tls.Certificate{
		Certificate: [][]byte{certDER},
		PrivateKey:  key,
	}

	return &cert, nil
}
