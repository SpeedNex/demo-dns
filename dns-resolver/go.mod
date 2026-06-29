module ocer-dns/dns-resolver

// go 1.25.0：项目的真实依赖要求（quic-go、x/* 等）。
// CI 环境必须安装 go 1.25.0 或设置 GOTOOLCHAIN=auto 自动下载。
// 离线/CI 环境若不允许下载 toolchain，请使用 GOTOOLCHAIN=local 并提前安装 go 1.25.0。
go 1.25.0

require (
	github.com/miekg/dns v1.1.72
	github.com/quic-go/quic-go v0.60.0
	github.com/redis/go-redis/v9 v9.7.0
	golang.org/x/sync v0.21.0
	gopkg.in/yaml.v3 v3.0.1
)

require (
	github.com/cespare/xxhash/v2 v2.3.0 // indirect
	github.com/dgryski/go-rendezvous v0.0.0-20200823014737-9f7001d12a5f // indirect
	github.com/google/go-cmp v0.7.0 // indirect
	github.com/kr/text v0.2.0 // indirect
	golang.org/x/crypto v0.51.0 // indirect
	golang.org/x/mod v0.36.0 // indirect
	golang.org/x/net v0.55.0 // indirect
	golang.org/x/sys v0.45.0 // indirect
	golang.org/x/text v0.38.0 // indirect
	golang.org/x/tools v0.45.0 // indirect
)
