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
	"strings"
	"sync/atomic"
	"time"
)

// DefaultCertDir 是证书文件的兜底路径。
// 即使 server.yaml 被清空，resolver 也会自动检查此路径。
// 部署时只需把 fullchain.pem + privkey.pem 放到该目录即可。
const DefaultCertDir = "/etc/ocer-dns/certs"

// resolveCertPaths 根据 SNI 域名查找证书路径。
// 支持泛域名：/etc/letsencrypt/live/dns1.ocerlinkdata.com/ 下的证书
// 可匹配 *.dns1.ocerlinkdata.com 的所有子域名。
// 查找顺序：
//  1. 尝试 SNI 精确匹配 /etc/letsencrypt/live/{domain}/
//  2. 逐级去前缀，匹配父域（支持泛域名 *.xxx 的存储路径）
//  3. /etc/ocer-dns/certs/fullchain.pem（自定义兜底路径）
//  4. 都找不到 → 返回空
func resolveCertPaths(domain string) (cert, key string) {
	if domain == "" {
		return fallbackCertPath()
	}

	// 逐级尝试：a.b.example.com → b.example.com → example.com
	for {
		leCert := "/etc/letsencrypt/live/" + domain + "/fullchain.pem"
		leKey := "/etc/letsencrypt/live/" + domain + "/privkey.pem"
		if _, err := os.Stat(leCert); err == nil {
			return leCert, leKey
		}
		// 去掉最左边一级子域名，尝试父域
		dot := strings.Index(domain, ".")
		if dot < 0 {
			break
		}
		domain = domain[dot+1:]
	}

	return fallbackCertPath()
}

// fallbackCertPath 检查自定义兜底路径 /etc/ocer-dns/certs/。
func fallbackCertPath() (string, string) {
	defCert := DefaultCertDir + "/fullchain.pem"
	defKey := DefaultCertDir + "/privkey.pem"
	if _, err := os.Stat(defCert); err == nil {
		return defCert, defKey
	}
	return "", ""
}

// LoadTLSConfig 加载 TLS 配置。
// 参数策略（优先级递减）：
//  1. server.yaml 配置了 certFile/keyFile → 使用指定路径（热加载）
//  2. server.yaml 未配置 → 按 SNI 域名自动查找证书：
//     a. /etc/letsencrypt/live/{sni_domain}/{fullchain,privkey}.pem
//     b. /etc/ocer-dns/certs/{fullchain,privkey}.pem
//  3. 所有路径都无证书 → 生成自签名（开发测试用）
//
// GetCertificate 回调内部维护原子缓存：
//   - 首次启动无证书时 → 自签名兜底
//   - 一旦成功加载过正式证书 → 后续加载失败使用缓存证书，不回退自签名
func LoadTLSConfig(certFile, keyFile, dnsDomain string) (*tls.Config, error) {
	// 快速路径：server.yaml 配置了显式路径
	if certFile != "" && keyFile != "" {
		return newHotReloadConfig(certFile, keyFile), nil
	}

	// 无配置 → 构建自动查找回调
	return &tls.Config{
		GetCertificate: func(hello *tls.ClientHelloInfo) (*tls.Certificate, error) {
			domain := hello.ServerName
			if domain == "" {
				domain = dnsDomain
			}
			certPath, keyPath := resolveCertPaths(domain)
			if certPath == "" {
				log.Printf("[TLS] SNI=%s 未找到证书 降级为自签名", domain)
				return generateSelfSignedCert()
			}
			cert, err := tls.LoadX509KeyPair(certPath, keyPath)
			if err != nil {
				log.Printf("[TLS] SNI=%s 加载失败 path=%s err=%v 降级为自签名", domain, certPath, err)
				return generateSelfSignedCert()
			}
			return &cert, nil
		},
		MinVersion: tls.VersionTLS12,
	}, nil
}

// newHotReloadConfig 创建热加载 TLS 配置（每次 TLS 握手从磁盘读取证书）。
// 支持原子缓存：加载失败时用缓存证书兜底，不中断现有连接。
func newHotReloadConfig(certFile, keyFile string) *tls.Config {
	log.Printf("[TLS] 将从 %s 加载证书（通过 GetCertificate 热加载）", certFile)

	var (
		cachedCert  atomic.Value
		hasRealCert atomic.Bool
	)

	if initialCert, err := tls.LoadX509KeyPair(certFile, keyFile); err == nil {
		cachedCert.Store(&initialCert)
		hasRealCert.Store(true)
		log.Printf("[TLS] 预加载证书 path=%s", certFile)
	}

	return &tls.Config{
		GetCertificate: func(hello *tls.ClientHelloInfo) (*tls.Certificate, error) {
			cert, err := tls.LoadX509KeyPair(certFile, keyFile)
			if err != nil {
				if cached := cachedCert.Load(); cached != nil {
					log.Printf("[TLS] 重载失败 err=%v 使用缓存证书", err)
					return cached.(*tls.Certificate), nil
				}
				log.Printf("[TLS] 无缓存证书 err=%v 降级为自签名", err)
				return generateSelfSignedCert()
			}
			cachedCert.Store(&cert)
			if !hasRealCert.Load() {
				hasRealCert.Store(true)
				log.Printf("[TLS] 首次成功加载 path=%s", certFile)
			}
			return &cert, nil
		},
		MinVersion: tls.VersionTLS12,
	}
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

	log.Printf("[TLS] 自签名证书已写入 pem=%s crt=%s（仅开发测试用，请勿用于生产）", pemPath, certPath)

	// 将证书写入 PEM 格式，方便 kdig +tls-ca 使用
	_ = os.WriteFile("/tmp/ocer-dns-ca.pem", pem.EncodeToMemory(pemBlock), 0644)

	cert := tls.Certificate{
		Certificate: [][]byte{certDER},
		PrivateKey:  key,
	}

	return &cert, nil
}
