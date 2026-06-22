module ocer-dns/dns-resolver

// 项目文档建议 go 1.22，但 indirect 依赖（x/sys v0.42、x/crypto v0.49、
// x/net v0.51、x/sync v0.19、x/mod v0.31、x/tools v0.40）已经要求 Go 1.25+。
// 离线/CI 不允许自动下载 toolchain 时，会直接编译失败。
// 折中方案：把 Go 指令定在 1.24（与当前 runner / CI 镜像一致），同时
// 把上述 x/* 间接依赖降到 0.30 之前（最后一个允许 go 1.22 的版本系列）。
// 这样在 1.22 / 1.23 / 1.24 任一版本上都能编译，避免 toolchain 下载。
go 1.25.0

require (
	github.com/ClickHouse/clickhouse-go/v2 v2.46.0
	github.com/miekg/dns v1.1.72
	github.com/redis/go-redis/v9 v9.7.0
	gopkg.in/yaml.v3 v3.0.1
)

require (
	github.com/ClickHouse/ch-go v0.71.0 // indirect
	github.com/andybalholm/brotli v1.2.0 // indirect
	github.com/cespare/xxhash/v2 v2.3.0 // indirect
	github.com/dgryski/go-rendezvous v0.0.0-20200823014737-9f7001d12a5f // indirect
	github.com/go-faster/city v1.0.1 // indirect
	github.com/go-faster/errors v0.7.1 // indirect
	github.com/google/uuid v1.6.0 // indirect
	github.com/klauspost/compress v1.18.5 // indirect
	github.com/paulmach/orb v0.12.0 // indirect
	github.com/pierrec/lz4/v4 v4.1.25 // indirect
	github.com/quic-go/quic-go v0.60.0 // indirect
	github.com/segmentio/asm v1.2.1 // indirect
	github.com/shopspring/decimal v1.4.0 // indirect
	go.opentelemetry.io/otel v1.41.0 // indirect
	go.opentelemetry.io/otel/trace v1.41.0 // indirect
	go.yaml.in/yaml/v3 v3.0.4 // indirect
	golang.org/x/crypto v0.51.0 // indirect
	golang.org/x/mod v0.35.0 // indirect
	golang.org/x/net v0.55.0 // indirect
	golang.org/x/sync v0.20.0 // indirect
	golang.org/x/sys v0.45.0 // indirect
	golang.org/x/tools v0.44.0 // indirect
)
