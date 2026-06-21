#!/usr/bin/env bash
# =============================================================================
#  build.sh — ocer-dns 一键构建脚本
#  一条命令同时编译两个 Go 客户端：
#      1) dns-resolver  (节点端 DNS 解析器 + 安装器)
#      2) geodns        (GeoDNS 入口服务)
#  输出目标: Linux amd64 + arm64，静态二进制（CGO_ENABLED=0），
#  适用于任何 Linux 发行版（glibc / musl 通用）。
# =============================================================================

set -euo pipefail

# ---------- 路径与基础变量 ----------
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# 输出到 public/build/
#   - 由 Nginx/Apache 直接作为静态目录分发
#   - 下载 URL 形如: https://<host>/build/dns-resolver-linux-amd64
OUT="$ROOT/portal-web/public/build"
STAMP="$(date +%Y%m%d-%H%M%S)"

# 客户端清单: "源目录:输出名:入口子路径"
# 源目录: 相对于本脚本根
# 输出名: 产物基础名（不含后缀）
# 入口子路径: go build 时用的 ./cmd/...
SERVICES=(
  "dns-resolver:dns-resolver:dns-resolver"
  "geodns:geodns:geodns"
)

# 目标平台矩阵: 通用 Linux
TARGETS=(
  "linux:amd64"
  "linux:arm64"
)

# ---------- 工具检查 ----------
if ! command -v go >/dev/null 2>&1; then
  echo "✗ Go 未安装或不在 PATH 中" >&2
  exit 1
fi

GOVER="$(go version | awk '{print $3}')"
echo "→ Go: $GOVER"
echo "→ 输出目录: $OUT"
mkdir -p "$OUT"

# ---------- 编译循环 ----------
BUILT=()
FAILED=0

for target in "${TARGETS[@]}"; do
  GOOS="${target%:*}"
  GOARCH="${target##*:}"
  ARCH_TAG="${GOOS}-${GOARCH}"

  for svc in "${SERVICES[@]}"; do
    DIR="${svc%%:*}"
    REST="${svc#*:}"
    NAME="${REST%%:*}"
    ENTRY="${REST##*:}"

    SRC="$ROOT/$DIR"
    OUT_BIN="$OUT/${NAME}-${ARCH_TAG}"

    if [ ! -d "$SRC" ]; then
      echo "  ✗ 源码目录不存在: $SRC" >&2
      FAILED=1
      continue
    fi
    if [ ! -f "$SRC/go.mod" ]; then
      echo "  ✗ 缺少 go.mod: $SRC" >&2
      FAILED=1
      continue
    fi

    echo "→ 编译 $NAME  ($ARCH_TAG)"
    if ! ( cd "$SRC" && \
           CGO_ENABLED=0 GOOS="$GOOS" GOARCH="$GOARCH" \
             go build -trimpath -ldflags="-s -w" \
               -o "$OUT_BIN" "./cmd/${ENTRY}" ); then
      echo "  ✗ 编译失败: $NAME ($ARCH_TAG)" >&2
      FAILED=1
      continue
    fi

    if [ -f "$OUT_BIN" ]; then
      chmod +x "$OUT_BIN"
      SIZE="$(du -h "$OUT_BIN" | cut -f1)"
      echo "  ✓ $OUT_BIN  ($SIZE)"
      BUILT+=("$OUT_BIN")
    fi
  done
done

# ---------- 产物摘要 ----------
echo ""
echo "=== 产物清单 ==="
if [ "${#BUILT[@]}" -gt 0 ]; then
  ls -la "$OUT"
  echo ""

  # 拷贝安装脚本（如果存在）— 一键安装脚本
  if [ -f "$ROOT/dns-resolver-install.sh" ]; then
    cp -f "$ROOT/dns-resolver-install.sh" "$OUT/dns-resolver-install.sh"
    chmod +x "$OUT/dns-resolver-install.sh"
    echo "✓ dns-resolver-install.sh 已就绪"
  fi

  if [ -f "$ROOT/geodns-install.sh" ]; then
    cp -f "$ROOT/geodns-install.sh" "$OUT/geodns-install.sh"
    chmod +x "$OUT/geodns-install.sh"
    echo "✓ geodns-install.sh 已就绪"
  fi

  # 生成 SHA256 校验和（部署到节点时可用 sha256sum -c 校验）
  if command -v shasum >/dev/null 2>&1; then
    ( cd "$OUT" && shasum -a 256 dns-resolver-* geodns-* > SHA256SUMS 2>/dev/null || true )
    echo "✓ SHA256SUMS 已生成"
  elif command -v sha256sum >/dev/null 2>&1; then
    ( cd "$OUT" && sha256sum dns-resolver-* geodns-* > SHA256SUMS 2>/dev/null || true )
    echo "✓ SHA256SUMS 已生成"
  fi

  echo "✓ 成功: ${#BUILT[@]} 个二进制"
  echo "  下载示例:"
  echo "    curl -O https://<host>/build/dns-resolver-linux-amd64"
  echo "    curl -O https://<host>/build/SHA256SUMS"
  echo "    sha256sum -c SHA256SUMS"
else
  echo "✗ 没有任何产物"
fi

# 写入构建指纹
cat > "$OUT/.build-stamp" <<EOF
built_at=$STAMP
go=$GOVER
EOF

exit "$FAILED"
