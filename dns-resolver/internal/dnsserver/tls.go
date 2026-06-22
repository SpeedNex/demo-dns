package dnsserver

import (
	"crypto/ecdsa"
	"crypto/elliptic"
	"crypto/rand"
	"crypto/tls"
	"crypto/x509"
	"crypto/x509/pkix"
	"log"
	"math/big"
	"net"
	"os"
	"time"
)

// LoadTLSConfig 加载或生成 TLS 证书。
// 如果 certFile/keyFile 非空则从文件加载；否则生成自签名证书（开发用）。
func LoadTLSConfig(certFile, keyFile string) (*tls.Config, error) {
	var cert tls.Certificate

	if certFile != "" && keyFile != "" {
		var err error
		cert, err = tls.LoadX509KeyPair(certFile, keyFile)
		if err != nil {
			return nil, err
		}
		log.Printf("tls: loaded certificate from %s", certFile)
	} else {
		log.Printf("tls: no certificate configured, generating self-signed (dev-only)")
		c, err := generateSelfSignedCert()
		if err != nil {
			return nil, err
		}
		cert = *c
	}

	return &tls.Config{
		Certificates: []tls.Certificate{cert},
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
		DNSNames:              []string{"localhost", "ocer-dns-resolver"},
	}

	certDER, err := x509.CreateCertificate(rand.Reader, tmpl, tmpl, &key.PublicKey, key)
	if err != nil {
		return nil, err
	}

	// 将临时证书写入文件，方便调试
	certPath := "/tmp/ocer-dns-dev.crt"
	keyPath := "/tmp/ocer-dns-dev.key"
	_ = os.WriteFile(certPath, certDER, 0644)

	keyBytes, err := x509.MarshalECPrivateKey(key)
	if err != nil {
		return nil, err
	}
	_ = os.WriteFile(keyPath, keyBytes, 0600)

	log.Printf("tls: self-signed cert written to %s / %s (dev-only, do not use in production)", certPath, keyPath)

	cert := tls.Certificate{
		Certificate: [][]byte{certDER},
		PrivateKey:  key,
	}

	return &cert, nil
}