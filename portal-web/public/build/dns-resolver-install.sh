#!/usr/bin/env bash
# =============================================================================
#  OcerDNS DNS Resolver Node One-click Install Script
#  Usage:
#    curl -fsSL https://<host>/build/dns-resolver-install.sh | bash -s -- \
#         --server=https://<host> \
#         --token=xxxxx \
#         --node-id=xxxxx
#
#  Behavior (2026-06-23 改造):
#    1) Detect OS / Architecture
#    2) Download dns-resolver-<os>-<arch> from ${server}/build/
#    3) Install to /usr/local/bin/dns-resolver
#    4) Run `dns-resolver install --server=... --token=... --node-id=... --start`
#    5) --start 默认开启:安装完成后自动拉起服务
#       - Linux 优先 systemd (写 /etc/systemd/system/dns-resolver.service + enable --now)
#       - macOS 使用 launchd (写 /Library/LaunchDaemons/com.ocerdns.dns-resolver.plist)
#       - 降级 nohup 后台进程(写 configs/dns-resolver.pid + configs/dns-resolver.log)
#       - 不要自动启动可用 --no-start
#  Supported platforms: linux/amd64, linux/arm64, darwin/amd64, darwin/arm64
# =============================================================================

set -euo pipefail

# ---------- Argument Parsing ----------
SERVER=""
TOKEN=""
NODE_ID=""
# 2026-06-22: 一键安装默认自动启动节点。
# 显式传 --no-start 可关闭(给想自己控制启动时机的运维用)。
AUTO_START=1
EXTRA_ARGS=()

usage() {
  cat <<EOF
Usage: $0 --server <url> --token <token> --node-id <id> [options]

Required:
  --server     Console Base URL, e.g. https://console.ocerlink.com
  --token      Node token issued by console
  --node-id    Node code

Options:
  --start          Auto-start dns-resolver after install (default: enabled)
  --no-start       Skip auto-start; you'll start it manually
  --verbose        Pass --verbose to dns-resolver install
  -h, --help       Show this help

Examples:
  # 1) Standard install with auto-start (recommended, one-shot)
  curl -fsSL https://console.ocerlink.com/build/dns-resolver-install.sh | bash -s -- \\
       --server=https://console.ocerlink.com \\
       --token=xxxxx \\
       --node-id=hk-01

  # 2) Install but don't start (start later manually)
  bash dns-resolver-install.sh --no-start --server=... --token=... --node-id=...
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --server=*)   SERVER="${1#*=}"; shift ;;
    --server)     SERVER="$2"; shift 2 ;;
    --token=*)    TOKEN="${1#*=}"; shift ;;
    --token)      TOKEN="$2"; shift 2 ;;
    --node-id=*)  NODE_ID="${1#*=}"; shift ;;
    --node-id)    NODE_ID="$2"; shift 2 ;;
    --start)      AUTO_START=1; shift ;;
    --no-start)   AUTO_START=0; shift ;;
    --verbose)    EXTRA_ARGS+=("--verbose"); shift ;;
    -h|--help)    usage; exit 0 ;;
    *)            echo "Unknown argument: $1" >&2; usage; exit 1 ;;
  esac
done

if [[ -z "$SERVER" || -z "$TOKEN" || -z "$NODE_ID" ]]; then
  echo "Missing required arguments" >&2
  usage
  exit 1
fi

# 2026-06-22: 把 --start/--no-start 转成 dns-resolver install 的对应 flag
[[ $AUTO_START -eq 1 ]] && EXTRA_ARGS+=("--start") || EXTRA_ARGS+=("--no-start")

# ---------- OS / Architecture Detection ----------
OS="$(uname -s | tr '[:upper:]' '[:lower:]')"
ARCH="$(uname -m)"

case "$OS" in
  linux)  SUFFIX_OS="linux" ;;
  darwin) SUFFIX_OS="darwin" ;;
  *)      echo "Unsupported OS: $OS (supported: linux, darwin)" >&2; exit 1 ;;
esac

case "$ARCH" in
  x86_64|amd64)   SUFFIX_ARCH="amd64" ;;
  aarch64|arm64)  SUFFIX_ARCH="arm64" ;;
  *)              echo "Unsupported arch: $ARCH" >&2; exit 1 ;;
esac

echo "Detected platform: ${SUFFIX_OS}/${SUFFIX_ARCH}  auto_start=$AUTO_START"

# ---------- Download Binary ----------
BIN_NAME="dns-resolver"
INSTALL_DIR="/usr/local/bin"
INSTALL_PATH="${INSTALL_DIR}/${BIN_NAME}"
DOWNLOAD_URL="${SERVER%/}/build/dns-resolver-${SUFFIX_OS}-${SUFFIX_ARCH}"

echo "Downloading: ${DOWNLOAD_URL}"

TMP_BIN="$(mktemp -t ocnd-bin-XXXXXX)"
trap 'rm -f "$TMP_BIN"' EXIT

if ! curl -fsSL --retry 3 -o "$TMP_BIN" "$DOWNLOAD_URL"; then
  echo "Download failed: $DOWNLOAD_URL" >&2
  exit 1
fi

chmod +x "$TMP_BIN"

# Verify downloaded file is a valid executable (ELF for Linux, Mach-O for macOS)
if ! file "$TMP_BIN" | grep -qE "ELF|Mach-O"; then
  echo "Downloaded content is not a valid executable (not ELF/Mach-O)" >&2
  exit 1
fi

# ---------- Install ----------
SUDO=""
if [[ $EUID -ne 0 ]]; then
  SUDO="sudo"
  echo "Not root, will use sudo to install to ${INSTALL_DIR}"
fi

$SUDO mkdir -p "$INSTALL_DIR"
$SUDO mv "$TMP_BIN" "$INSTALL_PATH"
$SUDO chmod 0755 "$INSTALL_PATH"

echo "Installed to ${INSTALL_PATH}"

# ---------- Verify Installation ----------
INSTALLED_VER="$("$INSTALL_PATH" --version 2>/dev/null || echo 'unknown')"
echo "Version: ${INSTALLED_VER}"

# ---------- Clean stale api_key (2026-06-30) ----------
# 重装前必须清除旧 api_key，确保 install 流程用新 token 重新获取凭据。
# 与 install.go::buildInstalledConfig() (line 306) 的 writeAPIKeyFile 配合，
# 让 Go binary 始终写入 fresh key，避免旧 key 残留导致 auth hash 不一致触发 401。
API_KEY_PATH="/usr/local/etc/dns-resolver/api_key"
if [[ -f "$API_KEY_PATH" ]]; then
    echo "Removing stale api_key: $API_KEY_PATH"
    $SUDO rm -f "$API_KEY_PATH"
fi

# ---------- Run Install Subcommand ----------
# 2026-06-22: 默认带 --start,dns-resolver install 完成后会自动拉起服务
# (systemd 优先,降级 nohup,见 dns-resolver/cmd/dns-resolver/install.go startService)。
echo "Registering node: ${NODE_ID}"
"$INSTALL_PATH" install \
  --server="$SERVER" \
  --token="$TOKEN" \
  --node-id="$NODE_ID" \
  "${EXTRA_ARGS[@]}"

# 2026-06-22: --start/--no-start 走 dns-resolver install 内部,这里只打收尾提示。
# 启动成功/失败的具体信息(走 systemd 还是 nohup、PID 文件、日志路径)
# 已经在 dns-resolver install 自己的输出里给出,这里不再重复。
if [[ "$OS" == "darwin" ]]; then
  echo ""
  echo "================================================================"
  echo "  Installation complete (macOS)"
  echo "================================================================"
  echo "  Binary:    ${INSTALL_PATH}"
  echo ""
  echo "  Manage (launchd):"
  echo "    sudo launchctl load /Library/LaunchDaemons/com.ocerdns.dns-resolver.plist"
  echo "    sudo launchctl unload /Library/LaunchDaemons/com.ocerdns.dns-resolver.plist"
  echo "    sudo launchctl list com.ocerdns.dns-resolver"
  echo ""
  echo "  Manage (nohup fallback):"
  echo "    \$(dirname \$(realpath ${INSTALL_PATH}))/configs/dns-resolver.pid  # pid file"
  echo "    \$(dirname \$(realpath ${INSTALL_PATH}))/configs/dns-resolver.log  # log file"
  echo ""
  echo "  Manual start (if you passed --no-start):"
  echo "    ${INSTALL_PATH} --config=configs/server.yaml"
  echo "================================================================"
else
  echo ""
  echo "================================================================"
  echo "  Installation complete"
  echo "================================================================"
  echo "  Binary:    ${INSTALL_PATH}"
  echo ""
  echo "  Manage (systemd):"
  echo "    systemctl status dns-resolver   # status"
  echo "    journalctl -u dns-resolver -f   # tail logs"
  echo "    systemctl restart dns-resolver  # restart"
  echo "    systemctl stop dns-resolver     # stop"
  echo ""
  echo "  Manage (nohup fallback):"
  echo "    \$(dirname \$(realpath ${INSTALL_PATH}))/configs/dns-resolver.pid  # pid file"
  echo "    \$(dirname \$(realpath ${INSTALL_PATH}))/configs/dns-resolver.log  # log file"
  echo ""
  echo "  Manual start (if you passed --no-start):"
  echo "    ${INSTALL_PATH} --config=configs/server.yaml"
  echo "================================================================"
fi