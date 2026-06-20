#!/usr/bin/env bash
# =============================================================================
#  OcerDNS 解析器 一键安装脚本
#  用法:
#    curl -sSL https://<host>/dist/install.sh | sh -s -- \
#         --server=https://<host> \
#         --token=ocnd_xxxxx \
#         --node-id=nd_xxxxx
#
#  行为:
#    1) 检测 OS / 架构
#    2) 从 ${server}/dist/bin/ 下载对应架构的 dns-resolver-linux-<arch>
#    3) 写入 /usr/local/bin/geo-dns
#    4) 执行 `geo-dns install --server=... --token=... --node-id=...`
# =============================================================================

set -euo pipefail

# ---------- 参数解析 ----------
SERVER=""
TOKEN=""
NODE_ID=""

usage() {
  cat <<EOF
Usage: $0 --server <url> --token <token> --node-id <id>

Options:
  --server     控制台 Base URL，例如 https://console.ocerlink.com
  --token      控制台签发的节点 token（ocnd_xxx）
  --node-id    节点编码（nd_xxx）
  -h, --help   显示此帮助
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --server)   SERVER="$2"; shift 2 ;;
    --token)    TOKEN="$2"; shift 2 ;;
    --node-id)  NODE_ID="$2"; shift 2 ;;
    -h|--help)  usage; exit 0 ;;
    *)          echo "✗ 未知参数: $1" >&2; usage; exit 1 ;;
  esac
done

if [[ -z "$SERVER" || -z "$TOKEN" || -z "$NODE_ID" ]]; then
  echo "✗ 缺少必填参数" >&2
  usage
  exit 1
fi

# ---------- OS / 架构检测 ----------
OS="$(uname -s | tr '[:upper:]' '[:lower:]')"
ARCH="$(uname -m)"

case "$OS" in
  linux)   SUFFIX_OS="linux" ;;
  *)       echo "✗ 不支持的操作系统: $OS（仅支持 Linux）" >&2; exit 1 ;;
esac

case "$ARCH" in
  x86_64|amd64)   SUFFIX_ARCH="amd64" ;;
  aarch64|arm64)  SUFFIX_ARCH="arm64" ;;
  *)              echo "✗ 不支持的架构: $ARCH" >&2; exit 1 ;;
esac

echo "→ 检测到平台: ${SUFFIX_OS}/${SUFFIX_ARCH}"

# ---------- 下载二进制 ----------
BIN_NAME="geo-dns"
INSTALL_DIR="/usr/local/bin"
INSTALL_PATH="${INSTALL_DIR}/${BIN_NAME}"
DOWNLOAD_URL="${SERVER%/}/dist/bin/dns-resolver-${SUFFIX_OS}-${SUFFIX_ARCH}"

echo "→ 下载: ${DOWNLOAD_URL}"

TMP_BIN="$(mktemp -t ocnd-bin-XXXXXX)"
trap 'rm -f "$TMP_BIN"' EXIT

if ! curl -fsSL --retry 3 -o "$TMP_BIN" "$DOWNLOAD_URL"; then
  echo "✗ 下载失败: $DOWNLOAD_URL" >&2
  exit 1
fi

chmod +x "$TMP_BIN"

# 校验下载到的文件至少是一个可执行 ELF（避免下载到 HTML 错误页）
if ! head -c 4 "$TMP_BIN" | grep -q "ELF"; then
  echo "✗ 下载内容不是合法的 ELF 可执行文件" >&2
  exit 1
fi

# ---------- 安装 ----------
SUDO=""
if [[ $EUID -ne 0 ]]; then
  SUDO="sudo"
  echo "→ 当前非 root，将使用 sudo 安装到 ${INSTALL_DIR}"
fi

$SUDO mkdir -p "$INSTALL_DIR"
$SUDO mv "$TMP_BIN" "$INSTALL_PATH"
$SUDO chmod 0755 "$INSTALL_PATH"

echo "✓ 已安装到 ${INSTALL_PATH}"

# ---------- 验证安装 ----------
INSTALLED_VER="$("$INSTALL_PATH" --version 2>/dev/null || echo 'unknown')"
echo "✓ 版本: ${INSTALLED_VER}"

# ---------- 执行 install 子命令 ----------
echo "→ 注册节点: ${NODE_ID}"
"$INSTALL_PATH" install \
  --server="$SERVER" \
  --token="$TOKEN" \
  --node-id="$NODE_ID"

echo ""
echo "✓ 安装完成。后续将作为系统服务运行。"
echo "  查看状态:   systemctl status ${BIN_NAME}   # 若使用 systemd"
echo "  查看日志:   journalctl -u ${BIN_NAME} -f"
